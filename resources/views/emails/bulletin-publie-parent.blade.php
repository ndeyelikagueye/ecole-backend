<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulletin de {{ $enfant->user->prenom }} {{ $enfant->user->nom }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .bulletin-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .stat {
            text-align: center;
            background: white;
            padding: 15px;
            border-radius: 8px;
            flex: 1;
            margin: 0 5px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .mention {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
        }
        .mention-excellent { background: #28a745; }
        .mention-tres-bien { background: #007bff; }
        .mention-bien { background: #17a2b8; }
        .mention-assez-bien { background: #ffc107; color: #333; }
        .mention-passable { background: #fd7e14; }
        .mention-insuffisant { background: #dc3545; }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #e9ecef;
            border-radius: 8px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 Portail Scolaire</h1>
        <h2>Bulletin de {{ $enfant->user->prenom }} {{ $enfant->user->nom }}</h2>
        <p>{{ $periodeLibelle }} - {{ $bulletin->annee_scolaire }}</p>
    </div>

    <div class="content">
        <p>Bonjour {{ $parent->prenom }} {{ $parent->nom }},</p>
        
        <p>Nous avons le plaisir de vous informer que le bulletin scolaire de votre enfant <strong>{{ $enfant->user->prenom }} {{ $enfant->user->nom }}</strong> pour le <strong>{{ $periodeLibelle }}</strong> est maintenant disponible.</p>

        <div class="bulletin-info">
            <h3>📊 Résultats de {{ $enfant->user->prenom }}</h3>
            
            <div class="stats">
                <div class="stat">
                    <div class="stat-value">{{ $bulletin->moyenne_generale }}/20</div>
                    <div>Moyenne générale</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ $bulletin->rang }}/{{ $bulletin->total_eleves }}</div>
                    <div>Classement</div>
                </div>
            </div>

            <p style="text-align: center;">
                <span class="mention mention-{{ strtolower(str_replace([' ', 'è'], ['-', 'e'], $bulletin->mention)) }}">
                    {{ $bulletin->mention }}
                </span>
            </p>

            @if($bulletin->appreciation)
            <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-top: 15px;">
                <strong>💬 Appréciation :</strong><br>
                {{ $bulletin->appreciation }}
            </div>
            @endif
        </div>

        <p>Vous pouvez consulter le bulletin détaillé en vous connectant à votre espace parent :</p>
        
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/login" class="btn">
                🔗 Accéder à mon espace parent
            </a>
        </div>

        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
            <strong>📧 Vos identifiants de connexion :</strong><br>
            Email : {{ $parent->email }}<br>
            Mot de passe : Si vous avez oublié votre mot de passe, contactez l'administration.
        </div>

        <p>Dans votre espace parent, vous pourrez :</p>
        <ul>
            <li>📄 Consulter le bulletin détaillé avec toutes les notes</li>
            <li>📈 Suivre l'évolution des résultats</li>
            <li>📝 Voir les appréciations des enseignants</li>
            <li>📞 Contacter l'établissement si nécessaire</li>
        </ul>
    </div>

    <div class="footer">
        <p><strong>🏫 {{ config('app.name', 'Portail Scolaire') }}</strong></p>
        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        <p>Pour toute question, contactez l'administration de l'établissement.</p>
    </div>
</body>
</html>