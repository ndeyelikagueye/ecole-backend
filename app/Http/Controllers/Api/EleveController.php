<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\User;
use App\Models\Classe;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EleveController extends Controller
{
    /**
     * Liste des élèves (Admin)
     */
    public function index(Request $request)
    {
        $query = Eleve::with(['user', 'classe']);

        // Filtrage par classe
        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('matricule_eleve', 'like', "%{$search}%");
        }

        $eleves = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des élèves',
            'data' => $eleves
        ]);
    }

    /**
     * Créer un élève (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'matricule_eleve' => 'required|string|unique:eleves',
            'date_naissance' => 'required|date',
            'adresse' => 'required|string',
            'telephone_parent' => 'required|string',
            'email_parent' => 'required|email',
            'classe_id' => 'required|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer ou récupérer le parent
        $parent = User::where('email', $request->email_parent)->first();
        if (!$parent) {
            // Créer le compte parent automatiquement
            $parent = User::create([
                'nom' => $request->nom, // Même nom de famille que l'élève
                'prenom' => 'Parent', // Prénom générique
                'email' => $request->email_parent,
                'password' => Hash::make('parent123'), // Mot de passe par défaut
                'role' => 'parent',
                'telephone' => $request->telephone_parent,
            ]);
        }

        // Créer l'utilisateur élève
        $user = User::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'eleve',
        ]);

        // Créer l'élève avec liaison au parent
        $eleve = Eleve::create([
            'matricule_eleve' => $request->matricule_eleve,
            'date_naissance' => $request->date_naissance,
            'adresse' => $request->adresse,
            'telephone_parent' => $request->telephone_parent,
            'email_parent' => $request->email_parent,
            'classe_id' => $request->classe_id,
            'user_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        // Créer une notification de bienvenue pour l'élève
        Notification::creerNotification(
            $user->id,
            'Bienvenue !',
            'Votre compte a été créé avec succès. Votre numéro d\'étudiant est : ' . $request->matricule_eleve,
            'inscription',
            'haute',
            auth()->id()
        );
        
        // Créer une notification pour le parent
        Notification::creerNotification(
            $parent->id,
            'Compte parent créé',
            'Votre compte parent a été créé pour suivre la scolarité de ' . $request->prenom . ' ' . $request->nom . '. Mot de passe par défaut : parent123',
            'inscription',
            'haute',
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Élève créé avec succès',
            'data' => $eleve->load(['user', 'classe'])
        ], 201);
    }

    /**
     * Afficher un élève (Admin)
     */
    public function show($id)
    {
        $eleve = Eleve::with(['user', 'classe', 'notes.matiere', 'bulletins', 'documents'])
                      ->find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de l\'élève',
            'data' => $eleve
        ]);
    }

    /**
     * Mettre à jour un élève (Admin)
     */
    public function update(Request $request, $id)
    {
        $eleve = Eleve::with('user')->find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $eleve->user_id,
            'password' => 'sometimes|required|string|min:6',
            'matricule_eleve' => 'sometimes|required|string|unique:eleves,matricule_eleve,' . $id,
            'date_naissance' => 'sometimes|required|date',
            'adresse' => 'sometimes|required|string',
            'telephone_parent' => 'sometimes|required|string',
            'email_parent' => 'sometimes|required|email',
            'classe_id' => 'sometimes|required|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Mettre à jour l'utilisateur
        $userData = $request->only(['nom', 'prenom', 'email']);
        if ($request->has('password')) {
            $userData['password'] = Hash::make($request->password);
        }
        $eleve->user->update($userData);

        // Mettre à jour l'élève
        $eleveData = $request->only([
            'matricule_eleve', 'date_naissance', 'adresse', 
            'telephone_parent', 'email_parent', 'classe_id'
        ]);
        $eleve->update($eleveData);

        return response()->json([
            'success' => true,
            'message' => 'Élève mis à jour avec succès',
            'data' => $eleve->load(['user', 'classe'])
        ]);
    }

    /**
     * Supprimer un élève (Admin)
     */
    public function destroy($id)
    {
        $eleve = Eleve::find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        $eleve->delete(); // Cascade delete grâce aux migrations

        return response()->json([
            'success' => true,
            'message' => 'Élève supprimé avec succès'
        ]);
    }

    /**
     * Dashboard élève
     */
    public function dashboard()
    {
        try {
            $user = auth()->user();
            
            // Rechercher l'élève correspondant à cet utilisateur
            $eleve = \App\Models\Eleve::where('user_id', $user->id)->with(['classe', 'user'])->first();
            
            if (!$eleve) {
                // Créer des stats avec les infos utilisateur de base
                $stats = [
                    'eleve' => [
                        'user' => $user,
                        'matricule_eleve' => 'EL' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
                        'classe' => ['nom' => 'Non assigné']
                    ],
                    'derniere_moyenne' => 0,
                    'notes_recentes' => [],
                    'bulletins_disponibles' => 0,
                    'notifications_non_lues' => 0,
                ];
            } else {
                // Notes récentes de cet élève spécifique
                $notesRecentes = \App\Models\Note::where('eleve_id', $eleve->id)
                    ->with('matiere')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
                
                // Compter les bulletins publiés
                $bulletinsDisponibles = \App\Models\Bulletin::where('eleve_id', $eleve->id)
                    ->where('publie', true)
                    ->count();
                
                // Compter les notifications non lues
                $notificationsNonLues = \App\Models\Notification::where('user_id', $user->id)
                    ->where('lu', false)
                    ->count();
                
                $stats = [
                    'eleve' => $eleve,
                    'derniere_moyenne' => $this->calculerMoyenne($eleve->id),
                    'notes_recentes' => $notesRecentes,
                    'bulletins_disponibles' => $bulletinsDisponibles,
                    'notifications_non_lues' => $notificationsNonLues,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Dashboard élève',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function calculerMoyenne($eleveId)
    {
        $notes = \App\Models\Note::where('eleve_id', $eleveId)->get();
        if ($notes->count() === 0) return 0;
        return round($notes->avg('valeur'), 2);
    }

    /**
     * Profil élève
     */
    public function profile()
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil élève',
            'data' => $eleve->load(['user', 'classe'])
        ]);
    }

    /**
     * Ma classe
     */
    public function maClasse()
    {
        try {
            $user = auth()->user();
            
            // Version simplifiée - rechercher directement dans la table eleves
            $eleve = \App\Models\Eleve::where('user_id', $user->id)->first();
            
            if (!$eleve) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil élève non trouvé. Vous n\'avez pas encore été inscrit comme élève.'
                ], 404);
            }
            
            // Récupérer les informations de base de la classe
            $classe = \App\Models\Classe::find($eleve->classe_id);
            
            if (!$classe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune classe assignée ou classe introuvable.'
                ], 404);
            }
            
            // Récupérer le professeur principal
            $enseignantPrincipal = null;
            if ($classe->enseignant_principal_id) {
                $enseignantUser = \App\Models\User::find($classe->enseignant_principal_id);
                if ($enseignantUser) {
                    $enseignantPrincipal = [
                        'user' => [
                            'nom' => $enseignantUser->nom,
                            'prenom' => $enseignantUser->prenom
                        ]
                    ];
                }
            }
            
            // Si pas de professeur principal trouvé, chercher via la table enseignants
            if (!$enseignantPrincipal && $classe->enseignant_principal_id) {
                $enseignant = \App\Models\Enseignant::where('user_id', $classe->enseignant_principal_id)
                    ->join('users', 'enseignants.user_id', '=', 'users.id')
                    ->select('users.nom', 'users.prenom')
                    ->first();
                    
                if ($enseignant) {
                    $enseignantPrincipal = [
                        'user' => [
                            'nom' => $enseignant->nom,
                            'prenom' => $enseignant->prenom
                        ]
                    ];
                }
            }
            
            // Récupérer tous les élèves de cette classe
            $elevesClasse = \App\Models\Eleve::where('classe_id', $classe->id)
                ->join('users', 'eleves.user_id', '=', 'users.id')
                ->select('eleves.*', 'users.nom', 'users.prenom')
                ->get()
                ->map(function($eleve) {
                    return [
                        'id' => $eleve->id,
                        'user' => [
                            'nom' => $eleve->nom,
                            'prenom' => $eleve->prenom
                        ]
                    ];
                });
            
            // Construire la réponse
            $classeData = [
                'id' => $classe->id,
                'nom' => $classe->nom,
                'niveau' => $classe->niveau ?? '',
                'enseignantPrincipal' => $enseignantPrincipal ?: [
                    'user' => [
                        'nom' => 'Non',
                        'prenom' => 'assigné'
                    ]
                ],
                'eleves' => $elevesClasse
            ];

            return response()->json([
                'success' => true,
                'message' => 'Ma classe',
                'data' => $classeData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement de la classe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes notes
     */
    public function mesNotes(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Rechercher les notes de cet utilisateur spécifique
            $query = \App\Models\Note::whereHas('eleve.user', function($q) use ($user) {
                $q->where('id', $user->id);
            })->with(['matiere', 'classe', 'eleve.user']);

            // Filtrage par période
            if ($request->has('periode')) {
                $query->where('periode', $request->periode);
            }

            // Filtrage par matière
            if ($request->has('matiere_id')) {
                $query->where('matiere_id', $request->matiere_id);
            }

            $notes = $query->orderBy('date_note', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Mes notes',
                'data' => $notes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notes par période
     */
    public function notesPeriode($periode)
    {
        $user = auth()->user();
        $eleve = $user->eleve;

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Profil élève non trouvé'
            ], 404);
        }

        $notes = $eleve->getNotesParMatiere($periode);
        $moyenne = $eleve->getMoyenneGenerale($periode);

        return response()->json([
            'success' => true,
            'message' => 'Notes de la période',
            'data' => [
                'periode' => $periode,
                'notes_par_matiere' => $notes,
                'moyenne_generale' => round($moyenne ?? 0, 2)
            ]
        ]);
    }

    /**
     * Mes bulletins
     */
    public function mesBulletins()
    {
        try {
            $user = auth()->user();
            
            // Rechercher les bulletins de cet utilisateur spécifique
            $bulletins = \App\Models\Bulletin::whereHas('eleve.user', function($q) use ($user) {
                $q->where('id', $user->id);
            })->where('publie', true)
              ->orderBy('created_at', 'desc')
              ->get();

            return response()->json([
                'success' => true,
                'message' => 'Mes bulletins',
                'data' => $bulletins
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des bulletins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Détails d'un bulletin
     */
    public function bulletinDetails($id)
    {
        try {
            $user = auth()->user();
            
            // Rechercher le bulletin de cet élève spécifique
            $bulletin = \App\Models\Bulletin::where('id', $id)
                ->whereHas('eleve.user', function($q) use ($user) {
                    $q->where('id', $user->id);
                })
                ->where('publie', true)
                ->with(['eleve.user', 'eleve.classe'])
                ->first();

            if (!$bulletin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulletin non trouvé ou non publié'
                ], 404);
            }

            // Récupérer les notes détaillées pour cette période
            $notesDetaillees = \App\Models\Note::where('eleve_id', $bulletin->eleve_id)
                ->where('periode', $bulletin->periode)
                ->with('matiere')
                ->get()
                ->groupBy('matiere.nom')
                ->map(function($notes, $matiere) {
                    $premiereNote = $notes->first();
                    $coefficient = $premiereNote->matiere->coefficient;
                    
                    // Si pas de coefficient défini, utiliser des valeurs par défaut selon la matière
                    if (!$coefficient || $coefficient == 0) {
                        $coefficient = match(strtolower($matiere)) {
                            'mathématiques', 'maths' => 4,
                            'français' => 4,
                            'anglais' => 3,
                            'sciences', 'physique', 'chimie' => 3,
                            'histoire-géographie', 'histoire', 'géographie' => 2,
                            'eps', 'sport' => 1,
                            'arts', 'musique', 'dessin' => 1,
                            default => 2
                        };
                    }
                    
                    return [
                        'matiere' => ['nom' => $matiere],
                        'notes' => $notes->pluck('valeur')->toArray(),
                        'moyenne' => round($notes->avg('valeur'), 2),
                        'coefficient' => $coefficient
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Détails du bulletin',
                'data' => [
                    'bulletin' => $bulletin,
                    'notes_detaillees' => $notesDetaillees
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du bulletin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes notifications
     */
    public function mesNotifications(Request $request)
    {
        try {
            $user = auth()->user();

            // Rechercher les notifications de cet utilisateur spécifique
            $query = \App\Models\Notification::where('user_id', $user->id)
                ->with('envoyePar:id,nom,prenom');

            // Filtrage par statut lu/non lu
            if ($request->has('lu')) {
                $query->where('lu', $request->boolean('lu'));
            }

            $notifications = $query->orderBy('created_at', 'desc')
                                  ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Mes notifications',
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLu($id)
    {
        $user = auth()->user();
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée'
            ], 404);
        }

        $notification->marquerCommeLue();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    /**
     * Compter les notifications non lues
     */
    public function countUnread()
    {
        $user = auth()->user();
        $count = $user->notifications()->where('lu', false)->count();

        return response()->json([
            'success' => true,
            'data' => ['count' => $count]
        ]);
    }
}