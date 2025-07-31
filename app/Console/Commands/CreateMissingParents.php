<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Eleve;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateMissingParents extends Command
{
    protected $signature = 'parents:create-missing';
    protected $description = 'Créer les comptes parents manquants pour les élèves existants';

    public function handle()
    {
        $this->info('Création des comptes parents manquants...');
        
        // Récupérer tous les élèves qui ont un email_parent
        $eleves = Eleve::whereNotNull('email_parent')
                      ->where('email_parent', '!=', '')
                      ->with('user')
                      ->get();
        
        $created = 0;
        $updated = 0;
        
        foreach ($eleves as $eleve) {
            // Vérifier si un parent avec cet email existe déjà
            $parent = User::where('email', $eleve->email_parent)->first();
            
            if (!$parent) {
                // Créer le compte parent
                $parent = User::create([
                    'nom' => $eleve->user->nom, // Même nom de famille
                    'prenom' => 'Parent',
                    'email' => $eleve->email_parent,
                    'password' => Hash::make('parent123'),
                    'role' => 'parent',
                    'telephone' => $eleve->telephone_parent,
                ]);
                
                $this->info("✅ Parent créé: {$parent->email} pour {$eleve->user->prenom} {$eleve->user->nom}");
                $created++;
            }
            
            // Mettre à jour le parent_id de l'élève si pas déjà fait
            if (!$eleve->parent_id) {
                $eleve->update(['parent_id' => $parent->id]);
                $this->info("🔗 Élève {$eleve->user->prenom} {$eleve->user->nom} lié au parent {$parent->email}");
                $updated++;
            }
        }
        
        $this->info("✨ Terminé ! {$created} parents créés, {$updated} élèves liés.");
        
        return 0;
    }
}