<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulletinController extends Controller
{
    /**
     * Liste des bulletins
     */
    public function index(Request $request)
    {
        $query = Bulletin::with(['eleve.user', 'eleve.classe']);

        // Filtrage par période
        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        // Filtrage par année scolaire
        if ($request->has('annee_scolaire')) {
            $query->where('annee_scolaire', $request->annee_scolaire);
        }

        // Filtrage par statut de publication
        if ($request->has('publie')) {
            $query->where('publie', $request->boolean('publie'));
        }

        // Filtrage par classe
        if ($request->has('classe_id')) {
            $query->whereHas('eleve', function($q) use ($request) {
                $q->where('classe_id', $request->classe_id);
            });
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('eleve.user', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%");
            })->orWhereHas('eleve', function($q) use ($search) {
                $q->where('matricule_eleve', 'like', "%{$search}%");
            });
        }

        $bulletins = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des bulletins',
            'data' => $bulletins
        ]);
    }

    /**
     * Créer/Générer un bulletin
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'periode' => 'required|in:trimestre_1,trimestre_2,trimestre_3',
            'annee_scolaire' => 'required|string|max:255',
            'eleve_id' => 'required|exists:eleves,id',
            'appreciation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier qu'un bulletin n'existe pas déjà pour cette période
        $existingBulletin = Bulletin::where([
            'eleve_id' => $request->eleve_id,
            'periode' => $request->periode,
            'annee_scolaire' => $request->annee_scolaire
        ])->first();

        if ($existingBulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Un bulletin existe déjà pour cette période'
            ], 422);
        }

        $eleve = Eleve::find($request->eleve_id);
        
        // Calculer la moyenne générale pour cette période
        $notes = $eleve->notes()->where('periode', $request->periode)->get();
        
        if ($notes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune note trouvée pour cette période'
            ], 422);
        }

        $moyenneGenerale = $notes->avg('valeur');

        // Déterminer la mention
        $mention = $this->getMention($moyenneGenerale);

        // Compter tous les élèves de la classe
        $totalElevesClasse = \App\Models\Eleve::where('classe_id', $eleve->classe_id)->count();
        
        // Calculer le rang en comparant avec les autres bulletins de la classe
        $bulletinsClasse = Bulletin::whereHas('eleve', function($query) use ($eleve) {
                $query->where('classe_id', $eleve->classe_id);
            })
            ->where('periode', $request->periode)
            ->where('annee_scolaire', $request->annee_scolaire)
            ->orderBy('moyenne_generale', 'desc')
            ->get();
        
        $rang = 1;
        foreach ($bulletinsClasse as $bulletinClasse) {
            if ($bulletinClasse->moyenne_generale > $moyenneGenerale) {
                $rang++;
            }
        }

        $bulletin = Bulletin::create([
            'periode' => $request->periode,
            'annee_scolaire' => $request->annee_scolaire,
            'moyenne_generale' => round($moyenneGenerale, 2),
            'mention' => $mention,
            'rang' => $rang,
            'total_eleves' => $totalElevesClasse,
            'appreciation' => $request->appreciation,
            'eleve_id' => $request->eleve_id,
            'publie' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bulletin généré avec succès',
            'data' => $bulletin->load(['eleve.user', 'eleve.classe'])
        ], 201);
    }

    /**
     * Afficher un bulletin
     */
    public function show($id)
    {
        $bulletin = Bulletin::with(['eleve.user', 'eleve.classe'])
                           ->find($id);

        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }

        // Récupérer les notes détaillées
        $notesDetaillees = $bulletin->getNotesDetaillees();

        return response()->json([
            'success' => true,
            'message' => 'Détails du bulletin',
            'data' => [
                'bulletin' => $bulletin,
                'notes_detaillees' => $notesDetaillees
            ]
        ]);
    }

    /**
     * Mettre à jour un bulletin
     */
    public function update(Request $request, $id)
    {
        $bulletin = Bulletin::find($id);

        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'moyenne_generale' => 'sometimes|required|numeric|min:0|max:20',
            'mention' => 'sometimes|required|in:Excellent,Très bien,Bien,Assez bien,Passable,Insuffisant',
            'rang' => 'sometimes|required|integer|min:1',
            'total_eleves' => 'sometimes|required|integer|min:1',
            'appreciation' => 'sometimes|nullable|string',
            'publie' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $bulletin->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Bulletin mis à jour avec succès',
            'data' => $bulletin->load(['eleve.user', 'eleve.classe'])
        ]);
    }

    /**
     * Supprimer un bulletin
     */
    public function destroy($id)
    {
        $bulletin = Bulletin::find($id);

        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }

        $bulletin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bulletin supprimé avec succès'
        ]);
    }

    /**
     * Publier un bulletin
     */
    public function publish($id)
    {
        $bulletin = Bulletin::find($id);

        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }

        $bulletin->update(['publie' => true]);

        // Envoyer un email à l'élève
        try {
            \Illuminate\Support\Facades\Mail::to($bulletin->eleve->user->email)
                ->send(new \App\Mail\BulletinPublie($bulletin));
                
            // Créer aussi une notification interne comme backup
            $periodeLibelle = match($bulletin->periode) {
                'trimestre_1' => '1er Trimestre',
                'trimestre_2' => '2ème Trimestre', 
                'trimestre_3' => '3ème Trimestre',
                default => $bulletin->periode
            };
            
            Notification::creerNotification(
                $bulletin->eleve->user_id,
                '📬 Email envoyé : Bulletin disponible',
                "Un email vous a été envoyé concernant votre bulletin du {$periodeLibelle}. Vérifiez votre boîte mail.",
                'bulletin',
                'normale',
                auth()->id(),
                [
                    'bulletin_id' => $bulletin->id,
                    'periode' => $bulletin->periode,
                    'moyenne' => $bulletin->moyenne_generale,
                    'mention' => $bulletin->mention,
                    'email_envoye' => true
                ],
                '/mes-bulletins'
            );
            
        } catch (\Exception $e) {
            // En cas d'erreur d'envoi d'email, créer une notification d'erreur
            Notification::creerNotification(
                $bulletin->eleve->user_id,
                '⚠️ Erreur envoi email',
                "Impossible d'envoyer l'email pour votre bulletin. Consultez directement votre espace élève.",
                'erreur',
                'haute',
                auth()->id(),
                ['erreur_email' => $e->getMessage()],
                '/mes-bulletins'
            );
        }
        
        // Envoyer notification au parent si il existe
        if ($bulletin->eleve->parent_id) {
            $parent = \App\Models\User::find($bulletin->eleve->parent_id);
            if ($parent) {
                try {
                    // Envoyer email au parent
                    \Illuminate\Support\Facades\Mail::to($parent->email)
                        ->send(new \App\Mail\BulletinPublieParent($bulletin, $parent));
                    
                    // Créer notification pour le parent
                    $periodeLibelle = match($bulletin->periode) {
                        'trimestre_1' => '1er Trimestre',
                        'trimestre_2' => '2ème Trimestre', 
                        'trimestre_3' => '3ème Trimestre',
                        default => $bulletin->periode
                    };
                    
                    Notification::creerNotification(
                        $parent->id,
                        '👨‍👩‍👧‍👦 Bulletin de votre enfant publié',
                        "Le bulletin de {$bulletin->eleve->user->prenom} {$bulletin->eleve->user->nom} pour le {$periodeLibelle} est maintenant disponible. Moyenne: {$bulletin->moyenne_generale}/20 - Mention: {$bulletin->mention}",
                        'bulletin',
                        'haute',
                        auth()->id(),
                        [
                            'bulletin_id' => $bulletin->id,
                            'enfant_nom' => $bulletin->eleve->user->prenom . ' ' . $bulletin->eleve->user->nom,
                            'periode' => $bulletin->periode,
                            'moyenne' => $bulletin->moyenne_generale,
                            'mention' => $bulletin->mention,
                            'email_envoye' => true
                        ],
                        '/parent/enfant/' . $bulletin->eleve->id . '/bulletins'
                    );
                    
                } catch (\Exception $e) {
                    // En cas d'erreur d'envoi d'email au parent
                    Notification::creerNotification(
                        $parent->id,
                        '⚠️ Bulletin disponible (erreur email)',
                        "Le bulletin de {$bulletin->eleve->user->prenom} {$bulletin->eleve->user->nom} est disponible mais l'email n'a pas pu être envoyé. Consultez votre espace parent.",
                        'bulletin',
                        'haute',
                        auth()->id(),
                        ['erreur_email' => $e->getMessage()],
                        '/parent/enfant/' . $bulletin->eleve->id . '/bulletins'
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulletin publié avec succès'
        ]);
    }

    /**
     * Générer des bulletins en masse
     */
    public function generateBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'periode' => 'required|in:trimestre_1,trimestre_2,trimestre_3',
            'annee_scolaire' => 'required|string|max:255',
            'classe_id' => 'required|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $eleves = Eleve::where('classe_id', $request->classe_id)->get();
        $bulletinsGeneres = [];
        $erreurs = [];

        foreach ($eleves as $eleve) {
            try {
                // Vérifier qu'un bulletin n'existe pas déjà
                $existingBulletin = Bulletin::where([
                    'eleve_id' => $eleve->id,
                    'periode' => $request->periode,
                    'annee_scolaire' => $request->annee_scolaire
                ])->first();

                if ($existingBulletin) {
                    $erreurs[] = "Bulletin déjà existant pour " . $eleve->user->full_name;
                    continue;
                }

                // Calculer la moyenne
                $notes = $eleve->notes()->where('periode', $request->periode)->get();
                
                if ($notes->isEmpty()) {
                    $erreurs[] = "Aucune note pour " . $eleve->user->full_name;
                    continue;
                }

                $moyenneGenerale = $notes->avg('valeur');
                $mention = $this->getMention($moyenneGenerale);

                $bulletin = Bulletin::create([
                    'periode' => $request->periode,
                    'annee_scolaire' => $request->annee_scolaire,
                    'moyenne_generale' => round($moyenneGenerale, 2),
                    'mention' => $mention,
                    'rang' => 1, // Sera recalculé après
                    'total_eleves' => $eleves->count(), // Total élèves de la classe
                    'eleve_id' => $eleve->id,
                    'publie' => false,
                ]);

                $bulletinsGeneres[] = $bulletin;
                
                // Envoyer un email si le bulletin est directement publié
                if ($request->has('publier_directement') && $request->boolean('publier_directement')) {
                    $bulletin->update(['publie' => true]);
                    
                    try {
                        \Illuminate\Support\Facades\Mail::to($eleve->user->email)
                            ->send(new \App\Mail\BulletinPublie($bulletin));
                    } catch (\Exception $e) {
                        $erreurs[] = "Erreur envoi email pour " . $eleve->user->full_name . ": " . $e->getMessage();
                    }
                }

            } catch (\Exception $e) {
                $erreurs[] = "Erreur pour " . $eleve->user->full_name . ": " . $e->getMessage();
            }
        }

        // Recalculer les rangs pour cette classe et période
        if (!empty($bulletinsGeneres)) {
            $this->recalculerRangsClasse($request->classe_id, $request->periode, $request->annee_scolaire);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulletins générés en masse',
            'data' => [
                'bulletins_generes' => count($bulletinsGeneres),
                'erreurs' => $erreurs,
                'bulletins' => $bulletinsGeneres
            ]
        ]);
    }

    /**
     * Générer PDF d'un bulletin
     */
    public function generatePdf($id)
    {
        $bulletin = Bulletin::with(['eleve.user', 'eleve.classe'])->find($id);

        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }

        // TODO: Implémenter la génération PDF
        // Pour l'instant, retourner les données structurées
        $notesDetaillees = $bulletin->getNotesDetaillees();

        return response()->json([
            'success' => true,
            'message' => 'Données pour génération PDF',
            'data' => [
                'bulletin' => $bulletin,
                'notes_detaillees' => $notesDetaillees,
                'pdf_url' => null // À implémenter
            ]
        ]);
    }

    /**
     * Méthodes privées
     */
    private function getMention($moyenne)
    {
        if ($moyenne >= 16) return 'Excellent';
        if ($moyenne >= 14) return 'Très bien';
        if ($moyenne >= 12) return 'Bien';
        if ($moyenne >= 10) return 'Assez bien';
        if ($moyenne >= 8) return 'Passable';
        return 'Insuffisant';
    }

    private function recalculerRangs($bulletins)
    {
        // Trier les bulletins par moyenne décroissante
        $bulletinsTries = collect($bulletins)->sortByDesc('moyenne_generale');
        
        $rang = 1;
        foreach ($bulletinsTries as $bulletin) {
            $bulletin->update(['rang' => $rang]);
            $rang++;
        }
    }
    
    private function recalculerRangsClasse($classeId, $periode, $anneeScolaire)
    {
        // Compter tous les élèves de la classe
        $totalElevesClasse = \App\Models\Eleve::where('classe_id', $classeId)->count();
        
        // Récupérer tous les bulletins de cette classe pour cette période
        $bulletins = Bulletin::whereHas('eleve', function($query) use ($classeId) {
                $query->where('classe_id', $classeId);
            })
            ->where('periode', $periode)
            ->where('annee_scolaire', $anneeScolaire)
            ->orderBy('moyenne_generale', 'desc')
            ->get();
        
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
            
            $moyennePrecedente = $bulletin->moyenne_generale;
            $rang++;
        }
    }
}