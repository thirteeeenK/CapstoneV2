<?php

namespace Tests\Feature\Security;

use App\Models\AdminAuditLog;
use App\Models\AdminModel;
use App\Models\DestinationModel;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminModel
    {
        return AdminModel::create([
            'name' => 'Auditor',
            'email' => 'auditor@sunny.example',
            'password' => bcrypt('secret123'),
        ]);
    }

    private function makePackageData(): array
    {
        $dest = DestinationModel::factory()->create();

        return [
            'destination_id' => $dest->id,
            'name' => 'Boracay Audit Deal',
            'type' => 'Vacation Deal',
            'price' => 3000,
            'days' => 3,
            'nights' => 2,
            'min_pax' => 2,
            'valid_from' => now()->format('Y-m-d'),
            'valid_to' => now()->addYear()->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function test_package_create_is_audited_without_old_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.packages.store'), $this->makePackageData())
            ->assertSessionHasNoErrors();

        $package = Package::where('name', 'Boracay Audit Deal')->latest()->first();
        $this->assertNotNull($package);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'auditable_type' => $package->getMorphClass(),
            'auditable_id' => $package->id,
        ]);

        $log = AdminAuditLog::where('auditable_id', $package->id)->first();
        $this->assertNull($log->old_values);
        $this->assertNotNull($log->new_values);
    }

    public function test_package_update_and_delete_are_audited(): void
    {
        $admin = $this->admin();
        $dest = DestinationModel::factory()->create();
        $package = Package::create(array_merge($this->makePackageData(), [
            'destination_id' => $dest->id,
        ]));

        $this->actingAs($admin, 'admin')
            ->put(route('admin.packages.update', $package->id), array_merge($this->makePackageData(), [
                'name' => 'Updated Audit Deal',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('admin_audit_logs', [
            'auditable_id' => $package->id,
            'admin_id' => $admin->id,
        ]);

        $updateLog = AdminAuditLog::where('auditable_id', $package->id)
            ->whereNotNull('old_values')
            ->latest()
            ->first();
        $this->assertNotNull($updateLog);
        $this->assertEquals('Boracay Audit Deal', $updateLog->old_values['name'] ?? null);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.packages.destroy', $package->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('admin_audit_logs', [
            'auditable_id' => $package->id,
            'admin_id' => $admin->id,
        ]);
    }
}
