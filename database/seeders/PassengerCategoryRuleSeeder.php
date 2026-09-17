<?php

namespace Database\Seeders;

use App\Models\PassengerCategoryRule;
use Illuminate\Database\Seeder;

class PassengerCategoryRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            [
                'category_name' => 'Adult',
                'display_label' => 'Regular Adult',
                'adjustment_type' => 'none',
                'amount' => 0.00000000,
                'is_active' => true,
            ],
            [
                'category_name' => 'Student',
                'display_label' => 'Student',
                'adjustment_type' => 'discount',
                'amount' => 50.00000000,
                'is_active' => true,
            ],
            [
                'category_name' => 'Senior Citizen',
                'display_label' => 'Senior Citizen',
                'adjustment_type' => 'discount',
                'amount' => 50.00000000,
                'is_active' => true,
            ],
            [
                'category_name' => 'PWD',
                'display_label' => 'PWD (Person with Disability)',
                'adjustment_type' => 'discount',
                'amount' => 50.00000000,
                'is_active' => true,
            ],
            [
                'category_name' => 'Child',
                'display_label' => 'Child (3-17)',
                'adjustment_type' => 'discount',
                'amount' => 50.00000000,
                'is_active' => true,
            ],
            [
                'category_name' => 'Infant',
                'display_label' => 'Infant (0-2)',
                'adjustment_type' => 'discount',
                'amount' => 50.00000000,
                'is_active' => true,
            ],
            [
                'category_name' => 'Foreigner',
                'display_label' => 'Foreign Tourist',
                'adjustment_type' => 'surcharge',
                'amount' => 200.00000000,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $rule) {
            PassengerCategoryRule::updateOrCreate(
                ['category_name' => $rule['category_name']],
                $rule
            );
        }
    }
}
