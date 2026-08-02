<?php

namespace App\Services;

use App\Enums\FieldGroup;
use App\Models\ContentPermission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Layers per-page/section/field-group grants on top of the coarse
 * "manage pages" Spatie permission. Super Admin, and anyone holding
 * "manage pages", is always unrestricted; everyone else needs an explicit
 * grant covering the page/section/field group being edited.
 */
class ContentPermissionGate
{
    public function isUnrestricted(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->can('manage pages');
    }

    public function allows(User $user, string $templateKey, string $sectionKey, FieldGroup $fieldGroup): bool
    {
        if ($this->isUnrestricted($user)) {
            return true;
        }

        return $this->grants($user)->contains(
            fn (ContentPermission $grant) => $this->matchesScope($grant->template_key, $templateKey)
                && $this->matchesScope($grant->section_key, $sectionKey)
                && $this->matchesScope($grant->field_group, $fieldGroup->value)
        );
    }

    public function allowsSection(User $user, string $templateKey, string $sectionKey): bool
    {
        if ($this->isUnrestricted($user)) {
            return true;
        }

        return $this->grants($user)->contains(
            fn (ContentPermission $grant) => $this->matchesScope($grant->template_key, $templateKey)
                && $this->matchesScope($grant->section_key, $sectionKey)
        );
    }

    public function hasAnyGrantForTemplate(User $user, string $templateKey): bool
    {
        if ($this->isUnrestricted($user)) {
            return true;
        }

        return $this->grants($user)->contains(
            fn (ContentPermission $grant) => $this->matchesScope($grant->template_key, $templateKey)
        );
    }

    public function hasAnyGrant(User $user): bool
    {
        return $this->isUnrestricted($user) || $this->grants($user)->isNotEmpty();
    }

    /**
     * Null means the user may access every template (unrestricted or holds
     * a wildcard grant); otherwise the specific template keys they hold a
     * grant for. Used to scope the pages list for granular-only users.
     */
    public function allowedTemplateKeys(User $user): ?array
    {
        if ($this->isUnrestricted($user)) {
            return null;
        }

        $grants = $this->grants($user);
        if ($grants->contains(fn (ContentPermission $grant) => $grant->template_key === ContentPermission::WILDCARD)) {
            return null;
        }

        return $grants->pluck('template_key')->unique()->values()->all();
    }

    public function grants(User $user): Collection
    {
        return $user->contentPermissions;
    }

    private function matchesScope(string $grantValue, string $actualValue): bool
    {
        return $grantValue === ContentPermission::WILDCARD || $grantValue === $actualValue;
    }
}
