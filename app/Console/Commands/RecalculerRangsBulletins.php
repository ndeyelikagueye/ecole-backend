<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bulletin;

class RecalculerRangsBulletins extends Command
{
    protected $signature = 'bulletins:recalculer-rangs';
    protected $description = 'Recalculer les rangs de tous les bulletins existants';

    public function handle()
    {
        $this->info('Recalcul des rangs des bulletins...');
        
        // Récupérer toutes les combinaisons classe/période/année
        $combinaisons = Bulletin::select('periode', 'annee_scolaire')
            ->join('eleves', 'bulletins.eleve_id', '=', 'eleves.id')
            ->select('bulletins.periode', 'bulletins.annee_scolaire', 'eleves.classe_id')
            ->distinct()
            ->get();
        
        $totalRecalcules = 0;
        
        foreach ($combinaisons as $combinaison) {
            $this->info("Traitement: Classe {$combinaison->classe_id} - {$combinaison->periode} - {$combinaison->annee_scolaire}");
            
            // Récupérer tous les bulletins de cette combinaison
            $bulletins = Bulletin::whereHas('eleve', function($query) use ($combinaison) {
                    $query->where('classe_id', $combinaison->classe_id);
                })
                ->where('periode', $combinaison->periode)
                ->where('annee_scolaire', $combinaison->annee_scolaire)
                ->orderBy('moyenne_generale', 'desc')
                ->get();
            
            if ($bulletins->isEmpty()) {
                continue;
            }
            
            // Le total d'élèves = tous les élèves de la classe
            $totalElevesClasse = \App\Models\Eleve::where('classe_id', $combinaison->classe_id)->count();
            
            // Assigner les rangs
            $rang = 1;
            $moyennePrecedente = null;
            $rangPrecedent = 1;
            
            foreach ($bulletins as $bulletin) {
                // Gestion des ex-aequo
                if ($moyennePrecedente !== null && $bulletin->moyenne_generale == $moyennePrecedente) {
                    $rangActuel = $rangPrecedent;
                } else {
                    $rangActuel = $rang;
                    $rangPrecedent = $rang;
                }
                
                $bulletin->update([
                    'rang' => $rangActuel,
                    'total_eleves' => $totalElevesClasse
                ]);
                
                $this->line("  - {$bulletin->eleve->user->prenom} {$bulletin->eleve->user->nom}: {$rangActuel}/{$totalElevesClasse}");
                
                $moyennePrecedente = $bulletin->moyenne_generale;
                $rang++;
                $totalRecalcules++;
            }
        }
        
        $this->info("✅ Terminé ! {$totalRecalcules} bulletins recalculés.");
        
        return 0;
    }
}