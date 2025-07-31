<?php

namespace App\Mail;

use App\Models\Bulletin;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BulletinPublieParent extends Mailable
{
    use Queueable, SerializesModels;

    public $bulletin;
    public $parent;

    public function __construct(Bulletin $bulletin, User $parent)
    {
        $this->bulletin = $bulletin;
        $this->parent = $parent;
    }

    public function build()
    {
        $periodeLibelle = match($this->bulletin->periode) {
            'trimestre_1' => '1er Trimestre',
            'trimestre_2' => '2ème Trimestre',
            'trimestre_3' => '3ème Trimestre',
            default => $this->bulletin->periode
        };

        return $this->subject("📄 Bulletin de {$this->bulletin->eleve->user->prenom} - {$periodeLibelle}")
                    ->view('emails.bulletin-publie-parent')
                    ->with([
                        'bulletin' => $this->bulletin,
                        'parent' => $this->parent,
                        'enfant' => $this->bulletin->eleve,
                        'periodeLibelle' => $periodeLibelle
                    ]);
    }
}