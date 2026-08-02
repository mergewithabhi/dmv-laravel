<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class SecurityProfile extends Component
{
    public string $code = '';

    public ?string $qrCode = null;

    public array $recoveryCodes = [];

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $this->authorizeAccess();
        abort_if(auth()->user()->hasEnabledTwoFactorAuthentication(), 422, 'Two-factor authentication is already enabled.');
        $enable(auth()->user(), true);
        auth()->user()->refresh();
        $this->qrCode = auth()->user()->twoFactorQrCodeSvg();
        $this->recoveryCodes = [];
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->authorizeAccess();
        abort_unless(
            filled(auth()->user()->two_factor_secret) && blank(auth()->user()->two_factor_confirmed_at),
            422,
            'Generate a setup code before confirming two-factor authentication.'
        );
        $validated = $this->validate([
            'code' => ['required', 'string', 'min:6', 'max:12'],
        ]);
        $confirm(auth()->user(), $validated['code']);
        auth()->user()->refresh();
        $this->recoveryCodes = auth()->user()->recoveryCodes();
        $this->qrCode = null;
        $this->code = '';
        activity('security')->causedBy(auth()->user())->log('enabled two factor authentication');
        session()->flash('success', 'Two-factor authentication is now enabled.');
    }

    public function regenerate(GenerateNewRecoveryCodes $generate): void
    {
        $this->authorizeAccess();
        abort_unless(auth()->user()->hasEnabledTwoFactorAuthentication(), 422);
        $generate(auth()->user());
        auth()->user()->refresh();
        $this->recoveryCodes = auth()->user()->recoveryCodes();
        activity('security')->causedBy(auth()->user())->log('regenerated recovery codes');
        session()->flash('success', 'New recovery codes were generated.');
    }

    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        $this->authorizeAccess();
        abort_if(
            config('cms.security.require_two_factor', true),
            422,
            'Two-factor authentication is required for CMS accounts.'
        );
        $disable(auth()->user());
        auth()->user()->refresh();
        $this->revokeOtherSessions();
        $this->reset(['code', 'qrCode', 'recoveryCodes']);
        activity('security')->causedBy(auth()->user())->log('disabled two factor authentication');
        session()->flash('success', 'Two-factor authentication was disabled.');
    }

    public function render()
    {
        $this->authorizeAccess();

        return view('livewire.admin.security-profile', [
            'enabled' => auth()->user()->hasEnabledTwoFactorAuthentication(),
            'pending' => filled(auth()->user()->two_factor_secret)
                && blank(auth()->user()->two_factor_confirmed_at),
            'required' => config('cms.security.require_two_factor', true),
        ])->title('Account Security')->layoutData(['heading' => 'Account Security']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('access admin'), 403);

        $confirmedAt = (int) session('auth.password_confirmed_at', 0);
        abort_unless(
            $confirmedAt > 0 && (time() - $confirmedAt) <= (int) config('auth.password_timeout'),
            403,
            'Recent password confirmation is required.'
        );
    }

    private function revokeOtherSessions(): void
    {
        $user = auth()->user();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table'))) {
            return;
        }

        DB::table(config('session.table'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', session()->getId())
            ->delete();
    }
}
