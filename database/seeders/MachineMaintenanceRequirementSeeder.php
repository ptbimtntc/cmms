<?php

namespace Database\Seeders;

use App\Models\MachineMaintenanceRequirement;
use Illuminate\Database\Seeder;

class MachineMaintenanceRequirementSeeder extends Seeder
{
    public function run(): void
    {
        // Migrates the machine types previously hardcoded in
        // PMSchedule::requiresOilChange().
        $machineTypes = [
            'NDE SC 2003/2007',
            'NDE SW',
            'NDE SW MONO',
            'NDB ONO',
            'NDB TRITON',
            'NDB MOT',
        ];

        foreach ($machineTypes as $machineType) {
            MachineMaintenanceRequirement::updateOrCreate(
                ['machine_type' => $machineType],
                ['requires_oil_change' => true]
            );
        }
    }
}
