<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulletin Disponible</title>
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 Bulletin Disponible</h1>
        <p>{{ $periodeLibelle }}</p>
    </div>
    
    <div class="content">
        <h2>Bonjour {{ $bulletin->eleve->user->prenom }} {{ $bulletin->eleve->user->nom }},</h2>
        
        <p>Nous avons le plaisir de vous informer que votre bulletin du <strong>{{ $periodeLibelle }}</strong> est maintenant disponible.</p>
        
        <div class="bulletin-info">
            <h3>📊 Résultats</h3>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value">{{ $bulletin->moyenne_generale }}/20</div>
                    <p>Moyenne Générale</p>
                </div>
                <div class="stat-box">
                    <div class="stat-value">{{ $bulletin->mention }}</div>
                    <p>Mention</p>
                </div>
            </div>
            
            <p><strong>Rang :</strong> {{ $bulletin->rang }}{{ $bulletin->rang == 1 ? 'er' : 'ème' }} sur {{ $bulletin->total_eleves }} élèves</p>
            
            @if($bulletin->appreciation)
                <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-top: 15px;">
                    <strong>Appréciation :</strong>
                    <em>{{ $bulletin->appreciation }}</em>
                </div>
            @endif
        </div>
        
        <p>Vous pouvez consulter le détail de vos notes et télécharger votre bulletin en vous connectant à votre espace élève.</p>
        
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url', 'http://localhost:4200') }}/mes-bulletins" class="btn">
                Consulter mon bulletin
            </a>
        </div>
        
        <p><strong>Félicitations pour vos efforts !</strong> Continuez ainsi pour maintenir ou améliorer vos résultats.</p>
    </div>
    
    <div class="footer">
        <p>Cet email a été envoyé automatiquement par le système de gestion scolaire.</p>
        <p>© {{ date('Y') }} Mon Portail Scolaire - Tous droits réservés</p>
    </div>
</body>
</html>