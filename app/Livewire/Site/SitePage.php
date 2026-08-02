<?php

namespace App\Livewire\Site;

use App\Domain\Content\FixedTemplateRenderer;
use App\Livewire\Site\Support\PublicInteractionNormalizer;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\NewsletterService;
use App\Services\ScheduleDataService;
use App\Services\SiteChromeService;
use App\Services\SubmissionService;
use App\Services\TurnstileService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.site')]
class SitePage extends Component
{
    public string $slug = 'home';

    public string $newsletterEmail = '';

    public bool $newsletterConsent = false;

    public bool $contactConsent = false;

    public bool $sponsorConsent = false;

    public array $contact = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'subject' => '',
        'message' => '',
    ];

    public array $sponsor = [
        'name' => '',
        'company' => '',
        'email' => '',
        'phone' => '',
        'level' => '',
        'message' => '',
    ];

    public string $website = '';

    public string $newsletterMessage = '';

    public string $contactMessage = '';

    public string $sponsorMessage = '';

    public string $turnstileToken = '';

    public function mount(string $slug = 'home'): void
    {
        $this->slug = $slug;
        $this->resolvePage();
    }

    public function submitNewsletter(NewsletterService $service, TurnstileService $turnstile): void
    {
        $this->guardForm('newsletter', $turnstile);
        $validated = $this->validate(
            [
                'newsletterEmail' => ['required', 'email:rfc,dns', 'max:254'],
                'newsletterConsent' => ['accepted'],
                'website' => ['nullable', 'max:0'],
            ],
            $this->validationMessages()
        );

        $service->subscribe($validated['newsletterEmail'], $validated['newsletterConsent']);
        $this->newsletterEmail = '';
        $this->newsletterConsent = false;
        $this->turnstileToken = '';
        $this->newsletterMessage = SiteSetting::value(
            'forms.newsletter_success',
            'Thanks. You are on the game-day list.'
        );
        $this->dispatch('site-form-complete');
    }

    public function submitContact(SubmissionService $service, TurnstileService $turnstile): void
    {
        $this->guardForm('contact', $turnstile);
        $validated = $this->validate(
            [
                'contact.name' => ['required', 'string', 'max:120'],
                'contact.email' => ['required', 'email:rfc,dns', 'max:254'],
                'contact.phone' => ['nullable', 'string', 'max:40'],
                'contact.subject' => ['required', 'string', 'max:120'],
                'contact.message' => ['required', 'string', 'max:5000'],
                'contactConsent' => ['accepted'],
                'website' => ['nullable', 'max:0'],
            ],
            $this->validationMessages()
        );

        $service->create(
            'contact',
            $validated['contact'] + ['consent' => $validated['contactConsent']],
            request()
        );
        $this->reset('contact');
        $this->contact = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
        $this->contactConsent = false;
        $this->turnstileToken = '';
        $this->contactMessage = SiteSetting::value(
            'forms.contact_success',
            'Message received. The DMV Warriors team will contact you.'
        );
        $this->dispatch('site-form-complete');
    }

    public function submitSponsor(SubmissionService $service, TurnstileService $turnstile): void
    {
        $this->guardForm('sponsor', $turnstile);
        $validated = $this->validate(
            [
                'sponsor.name' => ['required', 'string', 'max:120'],
                'sponsor.company' => ['required', 'string', 'max:160'],
                'sponsor.email' => ['required', 'email:rfc,dns', 'max:254'],
                'sponsor.phone' => ['required', 'string', 'max:40'],
                'sponsor.level' => ['required', 'string', 'max:80'],
                'sponsor.message' => ['nullable', 'string', 'max:5000'],
                'sponsorConsent' => ['accepted'],
                'website' => ['nullable', 'max:0'],
            ],
            $this->validationMessages()
        );

        $service->create(
            'sponsor',
            $validated['sponsor'] + ['consent' => $validated['sponsorConsent']],
            request()
        );
        $this->reset('sponsor');
        $this->sponsor = ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'level' => '', 'message' => ''];
        $this->sponsorConsent = false;
        $this->turnstileToken = '';
        $this->sponsorMessage = SiteSetting::value(
            'forms.sponsor_success',
            'Thank you. Our partnership team will contact you.'
        );
        $this->dispatch('site-form-complete');
    }

    public function render(
        FixedTemplateRenderer $renderer,
        PublicInteractionNormalizer $interactions,
        SiteChromeService $chrome,
        ScheduleDataService $scheduleData
    ) {
        $page = $this->resolvePage();
        $content = $interactions->normalize($renderer->render($page, [
            'newsletter_message' => $this->newsletterMessage
                ?: $this->firstErrorFor('newsletterEmail', 'newsletterConsent'),
            'contact_message' => $this->contactMessage
                ?: $this->firstErrorFor('contact.', 'contactConsent'),
            'sponsor_message' => $this->sponsorMessage
                ?: $this->firstErrorFor('sponsor.', 'sponsorConsent'),
            'form_errors' => $this->getErrorBag()->getMessages(),
        ]));
        $chromeData = $chrome->data();
        $calendarData = $scheduleData->calendarData();

        return view('livewire.site.site-page', compact('content'))
            ->title($page->seo_title ?: $page->title.' | DMV Warriors')
            ->layoutData(
                compact('page', 'calendarData') + $chromeData + [
                    'description' => $page->seo_description,
                    'structuredData' => $this->structuredData($page),
                ]
            );
    }

    private function resolvePage(): Page
    {
        return Page::query()
            ->with(['sections', 'ogMedia'])
            ->where('slug', $this->slug)
            ->published()
            ->firstOrFail();
    }

    private function guardForm(string $form, TurnstileService $turnstile): void
    {
        if ($this->website !== '') {
            throw ValidationException::withMessages(['website' => 'Unable to submit this form.']);
        }

        $key = "public-form:{$form}:".hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                $form === 'newsletter' ? 'newsletterEmail' : "{$form}.email" => SiteSetting::value(
                    'forms.validation_rate_limit',
                    'Too many attempts. Please wait before trying again.'
                ),
            ]);
        }

        RateLimiter::hit($key, 60);

        if (! $turnstile->verify($this->turnstileToken, request()->ip())) {
            throw ValidationException::withMessages([
                $form === 'newsletter' ? 'newsletterEmail' : "{$form}.email" => SiteSetting::value(
                    'forms.validation_human',
                    'Human verification failed. Please try again.'
                ),
            ]);
        }
    }

    private function validationMessages(): array
    {
        return [
            'required' => SiteSetting::value('forms.validation_required', 'Please complete this field.'),
            'email' => SiteSetting::value('forms.validation_email', 'Enter a valid email address.'),
            'accepted' => SiteSetting::value('forms.validation_consent', 'Please confirm your consent.'),
        ];
    }

    private function firstErrorFor(string ...$keysOrPrefixes): ?string
    {
        foreach ($keysOrPrefixes as $keyOrPrefix) {
            foreach ($this->getErrorBag()->getMessages() as $key => $messages) {
                if ($key === $keyOrPrefix || str_starts_with($key, $keyOrPrefix)) {
                    return $messages[0] ?? null;
                }
            }
        }

        return null;
    }

    private function structuredData(Page $page): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SportsTeam',
            'name' => 'DMV Warriors',
            'url' => url('/'),
            'logo' => asset('assets/bmv_logo_transparent.png'),
            'sport' => 'Basketball',
            'description' => $page->seo_description,
            'areaServed' => ['Washington D.C.', 'Maryland', 'Virginia'],
        ];
    }
}
