<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    use HasFactory;

    protected $fillable = [
        'periode',
        'annee_scolaire',
        'moyenne_generale',
        'mention',
        'rang',
        'total_eleves',
        'chemin_pdf',
        'publie',
        'appreciation',
        'eleve_id',
    ];

    protected function casts(): array
    {
        return [
            'moyenne_generale' => 'decimal:2',
            'publie' => 'boolean',
        ];
    }

    // Relations
    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    // Scopes
    public function scopePublies($query)
    {
        return $query->where('publie', true);
    }

    public function scopeParPeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }

    public function scopeParAnnee($query, $annee)
    {
        return $query->where('annee_scolaire', $annee);
    }

    // Accessors
    public function getMoyenneFormateeAttribute()
    {
        return number_format($this->moyenne_generale, 2) . '/20';
    }

    public function getRangFormatAttribute()
    {
        return $this->rang . '/' . $this->total_eleves;
    }

    public function getPeriodeLibelleAttribute()
    {
        return match($this->periode) {
            'trimestre_1' => '1er Trimestre',
            'trimestre_2' => '2ème Trimestre',
            'trimestre_3' => '3ème Trimestre',
            default => $this->periode
        };
    }

    // Méthodes utiles
    public function getNotesDetaillees()
    {
        $notes = $this->eleve->notes()
            ->where('periode', $this->periode)
            ->with('matiere')
            ->orderBy('matiere_id')
            ->orderBy('date_note')
            ->get();
        
        // Grouper par matière avec détails complets
        $notesParMatiere = [];
        
        foreach ($notes->groupBy('matiere.nom') as $nomMatiere => $notesMatiere) {
            $matiere = $notesMatiere->first()->matiere;
            
            // Calculer la moyenne de la matière
            $moyenneMatiere = $notesMatiere->avg('valeur');
            
            // Détailler chaque note avec son type
            $notesDetail = $notesMatiere->map(function($note) {
                return [
                    'valeur' => $note->valeur,
                    'type_evaluation' => $note->type_evaluation,
                    'date_note' => $note->date_note,
                    'commentaire' => $note->commentaire,
                    'type_libelle' => match($note->type_evaluation) {
                        'devoir' => 'Devoir',
                        'controle' => 'Contrôle',
                        'examen' => 'Examen',
                        'participation' => 'Participation',
                        default => ucfirst($note->type_evaluation)
                    }
                ];
            });
            
            $notesParMatiere[] = [
                'matiere' => [
                    'id' => $matiere->id,
                    'nom' => $matiere->nom,
                    'code' => $matiere->code ?? strtoupper(substr($matiere->nom, 0, 3))
                ],
                'moyenne' => round($moyenneMatiere, 2),
                'coefficient' => $matiere->coefficient ?? 1,
                'notes' => $notesDetail,
                'nombre_notes' => $notesMatiere->count(),
                'note_min' => $notesMatiere->min('valeur'),
                'note_max' => $notesMatiere->max('valeur')
            ];
        }
        
        return collect($notesParMatiere);
    }

    public function genererPdf()
    {
        // À implémenter pour la génération PDF
        return null;
    }
}