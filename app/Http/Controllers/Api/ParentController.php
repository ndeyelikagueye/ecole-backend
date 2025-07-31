<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Bulletin;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    /**
     * Liste des parents (Admin)
     */
    public function index(Request $request)
    {
        try {
            // Récupérer les parents avec leurs enfants
            $parents = \App\Models\User::where('role', 'parent')
                ->with([
                    'enfants' => function($query) {
                        $query->with(['user', 'classe']);
                    }
                ])
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Liste des parents',
                'data' => $parents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des parents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un parent (Admin)
     */
    public function store(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'telephone' => 'required|string',
                'adresse' => 'nullable|string',
                'profession' => 'nullable|string',
                'enfant_ids' => 'nullable|array',
                'enfant_ids.*' => 'exists:eleves,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Créer l'utilisateur parent
            $user = \App\Models\User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
                'role' => 'parent',
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'profession' => $request->profession,
            ]);
            
            // Associer les enfants au parent
            if ($request->has('enfant_ids') && is_array($request->enfant_ids)) {
                \App\Models\Eleve::whereIn('id', $request->enfant_ids)
                    ->update(['parent_id' => $user->id]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Parent créé avec succès',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un parent (Admin)
     */
    public function show($id)
    {
        try {
            $parent = \App\Models\User::where('role', 'parent')
                ->where('id', $id)
                ->with(['eleves.user', 'eleves.classe'])
                ->first();

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Détails du parent',
                'data' => $parent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un parent (Admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $parent = \App\Models\User::where('role', 'parent')->find($id);

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent non trouvé'
                ], 404);
            }

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'nom' => 'sometimes|required|string|max:255',
                'prenom' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'sometimes|nullable|string|min:6',
                'telephone' => 'sometimes|required|string',
                'adresse' => 'sometimes|nullable|string',
                'profession' => 'sometimes|nullable|string',
                'enfant_ids' => 'sometimes|nullable|array',
                'enfant_ids.*' => 'exists:eleves,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only(['nom', 'prenom', 'email', 'telephone', 'adresse', 'profession']);
            if ($request->has('password') && $request->password) {
                $updateData['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
            }

            $parent->update($updateData);
            
            // Mettre à jour les associations enfants
            if ($request->has('enfant_ids')) {
                // D'abord, retirer ce parent de tous les enfants
                \App\Models\Eleve::where('parent_id', $parent->id)
                    ->update(['parent_id' => null]);
                
                // Puis associer les nouveaux enfants
                if (is_array($request->enfant_ids) && !empty($request->enfant_ids)) {
                    \App\Models\Eleve::whereIn('id', $request->enfant_ids)
                        ->update(['parent_id' => $parent->id]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Parent mis à jour avec succès',
                'data' => $parent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un parent (Admin)
     */
    public function destroy($id)
    {
        try {
            $parent = \App\Models\User::where('role', 'parent')->find($id);

            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent non trouvé'
                ], 404);
            }

            $parent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Parent supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du parent',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard parent
     */
    public function dashboard()
    {
        try {
            $user = auth()->user();
            
            // Rechercher les enfants de ce parent
            $enfants = Eleve::where('parent_id', $user->id)
                ->orWhereHas('user', function($query) use ($user) {
                    // Si pas de parent_id, chercher par nom de famille similaire (temporaire)
                    $query->where('nom', $user->nom);
                })
                ->with(['user', 'classe'])
                ->get();

            $stats = [
                'parent' => $user,
                'nombre_enfants' => $enfants->count(),
                'enfants' => $enfants,
                'notifications_non_lues' => 0,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard parent',
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

    /**
     * Notes d'un enfant
     */
    public function notesEnfant($enfantId, Request $request)
    {
        try {
            $user = auth()->user();
            
            // Vérifier que cet enfant appartient à ce parent
            $enfant = Eleve::where('id', $enfantId)
                ->where(function($query) use ($user) {
                    $query->where('parent_id', $user->id)
                          ->orWhereHas('user', function($q) use ($user) {
                              $q->where('nom', $user->nom);
                          });
                })
                ->with(['user', 'classe'])
                ->first();

            if (!$enfant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enfant non trouvé ou non autorisé'
                ], 404);
            }

            $query = Note::where('eleve_id', $enfant->id)
                ->with(['matiere', 'classe']);

            // Filtrage par période
            if ($request->has('periode')) {
                $query->where('periode', $request->periode);
            }

            $notes = $query->orderBy('date_note', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Notes de l\'enfant',
                'data' => [
                    'enfant' => $enfant,
                    'notes' => $notes
                ]
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
     * Bulletins d'un enfant
     */
    public function bulletinsEnfant($enfantId)
    {
        try {
            $user = auth()->user();
            
            // Vérifier que cet enfant appartient à ce parent
            $enfant = Eleve::where('id', $enfantId)
                ->where(function($query) use ($user) {
                    $query->where('parent_id', $user->id)
                          ->orWhereHas('user', function($q) use ($user) {
                              $q->where('nom', $user->nom);
                          });
                })
                ->with(['user', 'classe'])
                ->first();

            if (!$enfant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enfant non trouvé ou non autorisé'
                ], 404);
            }

            $bulletins = Bulletin::where('eleve_id', $enfant->id)
                ->where('publie', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Bulletins de l\'enfant',
                'data' => [
                    'enfant' => $enfant,
                    'bulletins' => $bulletins
                ]
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
     * Détail d'un bulletin
     */
    public function bulletinDetail($enfantId, $bulletinId)
    {
        try {
            $user = auth()->user();
            
            // Vérifier que cet enfant appartient à ce parent
            $enfant = Eleve::where('id', $enfantId)
                ->where(function($query) use ($user) {
                    $query->where('parent_id', $user->id)
                          ->orWhereHas('user', function($q) use ($user) {
                              $q->where('nom', $user->nom);
                          });
                })
                ->first();

            if (!$enfant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enfant non trouvé ou non autorisé'
                ], 404);
            }

            $bulletin = Bulletin::where('id', $bulletinId)
                ->where('eleve_id', $enfant->id)
                ->where('publie', true)
                ->with(['eleve.user', 'eleve.classe'])
                ->first();

            if (!$bulletin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bulletin non trouvé ou non publié'
                ], 404);
            }

            // Récupérer les notes détaillées
            $notesDetaillees = Note::where('eleve_id', $enfant->id)
                ->where('periode', $bulletin->periode)
                ->with('matiere')
                ->get()
                ->groupBy('matiere.nom')
                ->map(function($notes, $matiere) {
                    return [
                        'matiere' => ['nom' => $matiere],
                        'notes' => $notes->pluck('valeur')->toArray(),
                        'moyenne' => round($notes->avg('valeur'), 2),
                        'coefficient' => $notes->first()->matiere->coefficient ?? 1
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Détail du bulletin',
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
}