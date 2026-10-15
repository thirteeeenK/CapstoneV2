<?php

namespace App\Services;

use App\Models\AdminModel;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SystemSettingsService
{
    public const SECTIONS = ['business', 'accounts', 'support', 'chatbot', 'notifications'];

    /** @var Collection<string, SystemSetting>|null */
    private ?Collection $records = null;

    private ?Request $currentRequest = null;

    /** @return array<string, mixed> */
    public function section(string $section): array
    {
        return array_replace_recursive($this->defaults($section), $this->record($section)?->values ?? []);
    }

    public function gracePeriodEnabled(): bool
    {
        return (bool) $this->section('accounts')['grace_period'];
    }

    public function revision(string $section): int
    {
        return $this->record($section)?->revision ?? 0;
    }

    public function businessContact(): string
    {
        $business = $this->section('business');
        $name = $this->revision('business') === 0 ? 'SUNNYTRIPS TRAVEL SERVICES' : $business['display_name'];
        $channels = implode(' or ', array_filter([$business['support_email'], $business['phone']]));

        return $name.($channels !== '' ? ' at '.$channels : ' through the human-support chat')
            .($business['address'] !== '' ? '. Address: '.str_replace("\n", ', ', $business['address']) : '');
    }

    /** @return array<string, mixed> */
    public function defaults(string $section): array
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        return $section === 'accounts'
            ? ['grace_period' => (bool) config('auth.account_deletion_grace_period', true)]
            : config('system_settings.'.$section);
    }

    /** @param array<string, mixed> $values */
    public function save(string $section, array $values, int $revision, AdminModel $admin): void
    {
        $this->defaults($section);
        $values = $this->normalize($section, $values);

        DB::transaction(function () use ($section, $values, $revision, $admin): void {
            // ON CONFLICT avoids aborting a PostgreSQL transaction on concurrent first saves.
            SystemSetting::query()->insertOrIgnore([
                'section' => $section, 'values' => '{}', 'revision' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $setting = SystemSetting::where('section', $section)->lockForUpdate()->firstOrFail();

            if ($setting->revision !== $revision) {
                throw ValidationException::withMessages([
                    'revision' => 'Another admin updated this section. Reload the page before saving again.',
                ]);
            }

            $oldValues = $setting->revision === 0 ? $this->defaults($section) : $setting->values;
            $setting->update(['values' => $values, 'revision' => $revision + 1, 'updated_by' => $admin->id]);
            AdminAuditService::recordAction($setting, 'settings_updated', ['section' => $section, 'values' => $oldValues], [
                'section' => $section, 'values' => $values,
            ], $admin);
        });

        $this->records = null;
    }

    private function record(string $section): ?SystemSetting
    {
        $request = app('request');
        if ($request !== $this->currentRequest) {
            $this->currentRequest = $request;
            $this->records = null;
        }

        $this->records ??= SystemSetting::all()->keyBy('section');

        return $this->records->get($section);
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function normalize(string $section, array $values): array
    {
        foreach ($this->defaults($section) as $key => $default) {
            if (is_bool($default)) {
                $values[$key] = filter_var($values[$key], FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($default)) {
                $values[$key] = (int) $values[$key];
            } elseif (is_string($default)) {
                $values[$key] = trim((string) ($values[$key] ?? ''));
            }
        }
        if ($section === 'support') {
            foreach ($values['schedule'] as &$day) {
                $day['enabled'] = filter_var($day['enabled'], FILTER_VALIDATE_BOOLEAN);
                $day['all_day'] = filter_var($day['all_day'], FILTER_VALIDATE_BOOLEAN);
            }
            unset($day);
        }
        if ($section === 'notifications') {
            $values['admin_ids'] = array_values(array_unique(array_map('intval', $values['admin_ids'] ?? [])));
        }

        return $values;
    }
}
