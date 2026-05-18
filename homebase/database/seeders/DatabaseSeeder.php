<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 🎭 Compte Admin - firstOrCreate évite les doublons
        $admin = User::firstOrCreate(
            ['email' => 'admin@homebase.local'], // Condition de recherche
            [ // Données à créer si inexistant
                'name' => 'Admin HomeBase',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        // Assigner le rôle seulement si le trait HasRoles est présent
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }

        // 👤 Compte User standard
        $user = User::firstOrCreate(
            ['email' => 'user@homebase.local'],
            [
                'name' => 'Utilisateur Test',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('user');
        }

        // 🧪 Compte Test original
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ]
        );

        $this->command->info('✅ Seeders exécutés avec succès !');
        $this->command->table(
            ['Email', 'Statut'], 
            [
                ['admin@homebase.local', 'Prêt 🔐'],
                ['user@homebase.local', 'Prêt 🔐'],
                ['test@example.com', 'Prêt 🔐'],
            ]
        );
    }
}