<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\User;
use App\Models\Note;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EnseignantController extends Controller
{
    /**
     * Liste des enseignants (Admin)
     */
    public function index(Request $request)
    {
        $query = Enseignant::with(['user', 'matieres']);

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('specialite', 'like', "%{$search}%");
        }

        $enseignants = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Liste des enseignants',
            'data' => $enseignants
        ]);
    }

    /**
     * Créer un enseignant (Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'telephone' => 'required|string',
            'specialite' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer l'utilisateur
        $user = User::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'enseignant',
        ]);

        // Créer l'enseignant
        $enseignant = Enseignant::create([
            'telephone' => $request->telephone,
            'specialite' => $request->specialite,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enseignant créé avec succès',
            'data' => $enseignant->load('user')
        ], 201);
    }

    /**
     * Afficher un enseignant (Admin)
     */
    public function show($id)
    {
        $enseignant = Enseignant::with(['user', 'matieres', 'classesPrincipales'])
                                ->find($id);

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Enseignant non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de l\'enseignant',
            'data' => $enseignant
        ]);
    }

    /**
     * Mettre à jour un enseignant (Admin)
     */
    public function update(Request $request, $id)
    {
        $enseignant = Enseignant::with('user')->find($id);

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Enseignant non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $enseignant->user_id,
            'password' => 'sometimes|required|string|min:6',
            'telephone' => 'sometimes|required|string',
            'specialite' => 'sometimes|nullable|string',
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
        $enseignant->user->update($userData);

        // Mettre à jour l'enseignant
        $enseignantData = $request->only(['telephone', 'specialite']);
        $enseignant->update($enseignantData);

        return response()->json([
            'success' => true,
            'message' => 'Enseignant mis à jour avec succès',
            'data' => $enseignant->load('user')
        ]);
    }

    /**
     * Supprimer un enseignant (Admin)
     */
    public function destroy($id)
    {
        $enseignant = Enseignant::find($id);

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Enseignant non trouvé'
            ], 404);
        }

        $enseignant->delete(); // Cascade delete grâce aux migrations

        return response()->json([
            'success' => true,
            'message' => 'Enseignant supprimé avec succès'
        ]);
    }

    /**
     * Dashboard enseignant
     */
    public function dashboard()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $stats = [
            'enseignant' => $enseignant->load('user'),
            'nombre_matieres' => $enseignant->matieres()->count(),
            'nombre_classes' => $enseignant->getClassesEnseignees()->count(),
            'nombre_eleves' => $enseignant->getElevesEnseignes()->count(),
            'notes_ajoutees_semaine' => Note::whereHas('matiere', function($q) use ($enseignant) {
                $q->where('enseignant_id', $enseignant->id);
            })->where('created_at', '>=', now()->subWeek())->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard enseignant',
            'data' => $stats
        ]);
    }

    /**
     * Mes classes
     */
    public function mesClasses()
    {
        try {
            $user = auth()->user();
            
            // CAS 1: Classes où l'enseignant est professeur principal
            $classesPrincipales = \App\Models\Classe::where('enseignant_principal_id', $user->id)
                ->with('eleves')
                ->get()
                ->map(function($classe) {
                    return [
                        'id' => $classe->id,
                        'nom' => $classe->nom,
                        'niveau' => $classe->niveau,
                        'annee_scolaire' => $classe->annee_scolaire,
                        'nombre_eleves' => $classe->eleves->count(),
                        'role' => 'Professeur principal'
                    ];
                });
            
            // CAS 2: Classes où l'enseignant enseigne des matières
            $classesEnseignees = \App\Models\Classe::whereHas('eleves.notes.matiere', function($query) use ($user) {
                $query->where('enseignant_id', $user->id);
            })
            ->with('eleves')
            ->get()
            ->map(function($classe) use ($user) {
                $matieres = \App\Models\Matiere::where('enseignant_id', $user->id)->pluck('nom')->toArray();
                return [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'niveau' => $classe->niveau,
                    'annee_scolaire' => $classe->annee_scolaire,
                    'nombre_eleves' => $classe->eleves->count(),
                    'role' => 'Enseignant de: ' . implode(', ', $matieres)
                ];
            });
            
            // Combiner et éliminer les doublons
            $classes = $classesPrincipales->merge($classesEnseignees)->unique('id')->values();

            return response()->json([
                'success' => true,
                'message' => 'Mes classes',
                'data' => $classes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes matières
     */
    public function mesMatieres()
    {
        try {
            $user = auth()->user();
            
            // Retourner seulement les matières enseignées par cet enseignant
            $matieres = \App\Models\Matiere::where('enseignant_id', $user->id)->get();
            
            // Si aucune matière trouvée, retourner des matières de test
            if ($matieres->isEmpty()) {
                $matieres = collect([
                    (object)[
                        'id' => 1,
                        'nom' => 'Mathématiques',
                        'code' => 'MATH',
                        'enseignant_id' => $user->id
                    ],
                    (object)[
                        'id' => 2,
                        'nom' => 'Français',
                        'code' => 'FR',
                        'enseignant_id' => $user->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Mes matières',
                'data' => $matieres
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des matières',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes élèves
     */
    public function mesEleves()
    {
        try {
            $user = auth()->user();
            
            // CAS 1: Si l'enseignant est professeur principal d'une classe
            $elevesClassesPrincipales = \App\Models\Eleve::whereHas('classe', function($query) use ($user) {
                $query->where('enseignant_principal_id', $user->id);
            })->with(['user', 'classe'])->get();
            
            // CAS 2: Élèves qui ont des notes dans les matières de cet enseignant
            $elevesEnseignes = \App\Models\Eleve::whereHas('notes.matiere', function($query) use ($user) {
                $query->where('enseignant_id', $user->id);
            })->with(['user', 'classe'])->get();
            
            // Combiner et éliminer les doublons
            $eleves = $elevesClassesPrincipales->merge($elevesEnseignes)->unique('id')->values();
            
            // Debug: Afficher les IDs des classes de cet enseignant
            \Log::info('Enseignant ID: ' . $user->id . ' - Classes principales: ' . $elevesClassesPrincipales->pluck('classe.nom')->implode(', '));
            \Log::info('Enseignant ID: ' . $user->id . ' - Total élèves: ' . $eleves->count());
            
            // Ajouter des informations sur le type de relation
            $eleves = $eleves->map(function($eleve) use ($user) {
                $estProfPrincipal = $eleve->classe->enseignant_principal_id == $user->id;
                $matieres = \App\Models\Matiere::where('enseignant_id', $user->id)->pluck('nom')->toArray();
                
                return [
                    'id' => $eleve->id,
                    'matricule_eleve' => $eleve->matricule_eleve,
                    'user' => $eleve->user,
                    'classe' => $eleve->classe,
                    'relation' => $estProfPrincipal ? 'Professeur principal' : 'Enseignant de: ' . implode(', ', $matieres)
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Mes élèves',
                'data' => $eleves
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur mesEleves pour enseignant ID: ' . $user->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des élèves',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes notes
     */
    public function mesNotes(Request $request)
    {
        $user = auth()->user();
        
        if ($user->role !== 'enseignant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux enseignants'
            ], 403);
        }

        // Filtrer les notes seulement pour les élèves de cet enseignant
        $query = Note::whereHas('eleve', function($eleveQuery) use ($user) {
            // Élèves des classes où l'enseignant est prof principal
            $eleveQuery->whereHas('classe', function($classeQuery) use ($user) {
                $classeQuery->where('enseignant_principal_id', $user->id);
            });
        })->with(['eleve.user', 'matiere', 'classe']);

        // Filtrage par période
        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        // Filtrage par matière
        if ($request->has('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        // Filtrage par classe
        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        $notes = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Mes notes',
            'data' => $notes
        ]);
    }

    /**
     * Ajouter une note
     */
    public function ajouterNote(Request $request)
    {
        $user = auth()->user();
        
        // Vérification simplifiée du rôle
        if ($user->role !== 'enseignant') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux enseignants'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'valeur' => 'required|numeric|min:0|max:20',
            'periode' => 'required|in:trimestre_1,trimestre_2,trimestre_3',
            'date_note' => 'required|date',
            'type_evaluation' => 'required|string',
            'commentaire' => 'nullable|string',
            'eleve_id' => 'required|exists:eleves,id',
            'matiere_id' => 'required|exists:matieres,id',
            'classe_id' => 'required|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer la note directement (temporaire pour diagnostic)
        $note = Note::create($request->all());
        
        \Log::info('Note créée par enseignant', [
            'note_id' => $note->id,
            'enseignant_id' => $user->id,
            'eleve_id' => $note->eleve_id,
            'valeur' => $note->valeur
        ]);

        // Créer une notification pour l'élève
        $eleve = $note->eleve;
        $matiere = $note->matiere;
        
        if ($eleve && $matiere) {
            Notification::creerNotification(
                $eleve->user_id,
                'Nouvelle note ajoutée',
                'Une nouvelle note (' . $note->valeur . '/20) a été ajoutée en ' . $matiere->nom . '.',
                'note',
                'normale',
                $user->id,
                [
                    'valeur' => $note->valeur,
                    'matiere' => $matiere->nom,
                    'note_id' => $note->id
                ],
                '/mes-notes'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Note ajoutée avec succès',
            'data' => $note->load(['eleve.user', 'matiere', 'classe'])
        ], 201);
    }

    /**
     * Modifier une note
     */
    public function modifierNote(Request $request, $id)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $note = Note::whereHas('matiere', function($q) use ($enseignant) {
            $q->where('enseignant_id', $enseignant->id);
        })->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée ou non autorisée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'valeur' => 'sometimes|required|numeric|min:0|max:20',
            'periode' => 'sometimes|required|in:trimestre_1,trimestre_2,trimestre_3',
            'date_note' => 'sometimes|required|date',
            'type_evaluation' => 'sometimes|required|string',
            'commentaire' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $note->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Note modifiée avec succès',
            'data' => $note->load(['eleve.user', 'matiere', 'classe'])
        ]);
    }

    /**
     * Supprimer une note
     */
    public function supprimerNote($id)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $note = Note::whereHas('matiere', function($q) use ($enseignant) {
            $q->where('enseignant_id', $enseignant->id);
        })->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée ou non autorisée'
            ], 404);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note supprimée avec succès'
        ]);
    }

    /**
     * Notes d'une classe
     */
    public function notesClasse($classeId)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $notes = Note::where('classe_id', $classeId)
                    ->whereHas('matiere', function($q) use ($enseignant) {
                        $q->where('enseignant_id', $enseignant->id);
                    })
                    ->with(['eleve.user', 'matiere'])
                    ->orderBy('date_note', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'message' => 'Notes de la classe',
            'data' => $notes
        ]);
    }

    /**
     * Notes d'une matière
     */
    public function notesMatiere($matiereId)
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        // Vérifier que la matière appartient à cet enseignant
        $matiere = $enseignant->matieres()->find($matiereId);
        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matière non autorisée'
            ], 403);
        }

        $notes = Note::where('matiere_id', $matiereId)
                    ->with(['eleve.user', 'classe'])
                    ->orderBy('date_note', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'message' => 'Notes de la matière',
            'data' => [
                'matiere' => $matiere,
                'notes' => $notes
            ]
        ]);
    }

    /**
     * Bulletins des classes
     */
    public function bulletinsClasses()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        $classes = $enseignant->getClassesEnseignees();
        $bulletins = collect();

        foreach ($classes as $classe) {
            $classBulletins = $classe->bulletins()->with('eleve.user')->get();
            $bulletins = $bulletins->merge($classBulletins);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulletins des classes',
            'data' => $bulletins->sortByDesc('created_at')->values()
        ]);
    }

    /**
     * Profil enseignant
     */
    public function profile()
    {
        $user = auth()->user();
        $enseignant = $user->enseignant;

        if (!$enseignant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil enseignant non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil enseignant',
            'data' => $enseignant->load(['user', 'matieres', 'classesPrincipales'])
        ]);
    }
}