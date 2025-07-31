<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bulletin;

class RefreshBulletinsDetails extends Command
{
    protected $signature = 'bulletins:refresh-details';
    protected $description = 'Rafraîchir les détails de tous les bulletins existants';

    public function handle()
    {
        $this->info('Rafraîchissement des détails des bulletins...');
        
        $bulletins = Bulletin::with(['eleve.user', 'eleve.classe'])->get();
        
        $this->info("Nombre de bulletins à traiter: {$bulletins->count()}");
        
        foreach ($bulletins as $bulletin) {
            $this->info("Traitement: {$bulletin->eleve->user->prenom} {$bulletin->eleve->user->nom} - {$bulletin->periode}");
            
            // Tester la méthode getNotesDetaillees pour ce bulletin
            try {
                $notesDetaillees = $bulletin->getNotesDetaillees();
                
                $this->line("  ✅ Détails générés: {$notesDetaillees->count()} matières");
                
                foreach ($notesDetaillees as $noteMatiere) {
                    $this->line("    - {$noteMatiere['matiere']['nom']} (Coef: {$noteMatiere['coefficient']}) - Moyenne: {$noteMatiere['moyenne']}/20");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Erreur pour ce bulletin: " . $e->getMessage());
            }
            
            $this->line('');
        }
        
        $this->info('✅ Rafraîchissement terminé !');
        
        return 0;
    }
}