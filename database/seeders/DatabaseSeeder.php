<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ServiceRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1 Admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Student A
        $studentA = User::create([
            'name' => 'Student A',
            'email' => 'studenta@example.com',
            'password' => Hash::make('password'),
            'role' => 'student',
        ]);

        // Student B
        $studentB = User::create([
            'name' => 'Student B',
            'email' => 'studentb@example.com',
            'password' => Hash::make('password'),
            'role' => 'student',
        ]);

        // Seed requests for Student A
        ServiceRequest::create([
            'user_id' => $studentA->id,
            'requester_name' => $studentA->name,
            'requester_email' => $studentA->email,
            'item_name' => 'Projector HDMI Cable',
            'quantity' => 1,
            'purpose' => 'Capstone presentation setup',
            'status' => 'pending',
        ]);

        // Seed requests for Student B
        ServiceRequest::create([
            'user_id' => $studentB->id,
            'requester_name' => $studentB->name,
            'requester_email' => $studentB->email,
            'item_name' => 'Whiteboard Markers',
            'quantity' => 3,
            'purpose' => 'Laboratory group brainstorming',
            'status' => 'approved',
        ]);
    }
}

