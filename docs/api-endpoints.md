# Endpoints de l'API Blog

Ce document résume les routes HTTP exposées par les contrôleurs API du module Blog ainsi que les rôles attendus et les payloads principaux.

## Blog (`/v1/blog`)

| Méthode | Chemin | Description | Rôle minimum |
| --- | --- | --- | --- |
| GET | `/v1/blog/count` | Compter les blogs. | `ROLE_ADMIN` |
| GET | `/v1/blog` | Lister les blogs. | `ROLE_ADMIN` |
| GET | `/v1/blog/ids` | Récupérer uniquement les identifiants. | `ROLE_ADMIN` |
| GET | `/v1/blog/{id}` | Obtenir un blog spécifique (UUID v1). | `ROLE_ADMIN` |
| POST | `/v1/blog` | Créer un blog. | `ROLE_ROOT` |
| PUT | `/v1/blog/{id}` | Remplacer un blog existant. | `ROLE_ROOT` |
| PATCH | `/v1/blog/{id}` | Mettre à jour partiellement un blog. | `ROLE_ROOT` |
| DELETE | `/v1/blog/{id}` | Supprimer un blog. | `ROLE_ROOT` |

### Exemple de payload JSON pour la création / mise à jour d'un blog

```json
{
  "title": "Engineering Insights",
  "blogSubtitle": "L'actualité de l'équipe",
  "author": "8b21f060-7d8d-11ee-b962-0242ac120002",
  "logo": "https://cdn.example.com/blogs/eng/logo.svg",
  "teams": ["platform", "data"],
  "visible": true,
  "slug": "engineering-insights",
  "color": "#005BBB"
}
```

## Post (`/v1/post`)

| Méthode | Chemin | Description | Rôle minimum |
| --- | --- | --- | --- |
| GET | `/v1/post/count` | Compter les posts. | `ROLE_ADMIN` |
| GET | `/v1/post` | Lister les posts. | `ROLE_ADMIN` |
| GET | `/v1/post/ids` | Récupérer les identifiants des posts. | `ROLE_ADMIN` |
| GET | `/v1/post/{id}` | Obtenir un post spécifique (UUID v1). | `ROLE_ADMIN` |
| POST | `/v1/post` | Créer un post. | `ROLE_ROOT` |
| PUT | `/v1/post/{id}` | Remplacer un post existant. | `ROLE_ROOT` |
| PATCH | `/v1/post/{id}` | Mettre à jour partiellement un post. | `ROLE_ROOT` |

### Exemple de payload JSON pour la création / mise à jour d'un post

```json
{
  "title": "Comment optimiser Symfony",
  "summary": "Un tour d'horizon des pratiques de performance.",
  "content": "<p>Voici les étapes...</p>",
  "url": "https://example.com/blog/optimiser-symfony",
  "author": "985b95e0-7d8d-11ee-b962-0242ac120002",
  "blog": "8b21f060-7d8d-11ee-b962-0242ac120002",
  "tags": ["symfony", "performance"],
  "mediaIds": [
    "a6cfb33c-7d8d-11ee-b962-0242ac120002"
  ],
  "publishedAt": "2024-01-15T09:30:00+00:00"
}
```

> `blog` fait référence à l'identifiant du blog parent et `mediaIds` liste des UUID de médias déjà existants. Les champs `summary`, `content` et `title` peuvent être laissés à `null` pour un brouillon, mais doivent respecter les contraintes de longueur lorsqu'ils sont fournis.

## Commentaire (plateforme `/v1/platform`)

Ces routes sont utilisées par l'interface "plateforme" et requièrent que l'utilisateur soit pleinement authentifié (`IS_AUTHENTICATED_FULLY`).

| Méthode | Chemin | Description | Rôle minimum |
| --- | --- | --- | --- |
| GET | `/v1/platform/post/{post}/comments` | Récupérer l'arborescence complète des commentaires d'un post. | Utilisateur authentifié |
| POST | `/v1/platform/post/{post}/comment` | Créer un nouveau commentaire sur un post. | Utilisateur authentifié |
| POST | `/v1/platform/comment/{comment}/comment` | Répondre à un commentaire existant (création d'un enfant). | Utilisateur authentifié |
| PUT | `/v1/platform/comment/{comment}` | Modifier le contenu d'un commentaire. | Utilisateur authentifié (auteur requis) |
| DELETE | `/v1/platform/comment/{comment}` | Supprimer un commentaire. | Utilisateur authentifié (auteur requis) |
| POST | `/v1/platform/comment/{comment}/like` | Aimer un commentaire (crée un like). | Utilisateur authentifié |
| POST | `/v1/platform/comment/{like}/dislike` | Retirer son like d'un commentaire. | Utilisateur authentifié |

### Exemple de payload JSON pour créer / répondre à un commentaire

```json
{
  "content": "Merci pour cet article, très instructif !"
}
```

> Lors d'une réponse (`/v1/platform/comment/{comment}/comment`), le commentaire parent est fourni dans l'URL et le backend rattache automatiquement le nouveau commentaire.

### Exemple de réponse JSON pour `/v1/platform/post/{post}/comments`

```json
[
  {
    "id": "f8b1b254-a012-11ee-b962-0242ac120002",
    "content": "Super billet.",
    "publishedAt": "2024-02-05T12:45:00+00:00",
    "user": {
      "id": "42",
      "displayName": "Alice"
    },
    "likes": [
      {
        "id": "aa31d3b8-a012-11ee-b962-0242ac120002",
        "user": {
          "id": "99",
          "displayName": "Bob"
        }
      }
    ],
    "children": [
      {
        "id": "0d4a71c6-a013-11ee-b962-0242ac120002",
        "content": "Merci !",
        "publishedAt": "2024-02-05T13:10:00+00:00",
        "user": {
          "id": "42",
          "displayName": "Alice"
        },
        "likes": [],
        "children": []
      }
    ]
  }
]
```

## Commentaire (lecture publique `/public`)

Ces routes sont utilisées pour l'affichage public (sans authentification) et renvoient des données paginées prêtes à être consommées par le frontend.

| Méthode | Chemin | Description | Rôle minimum |
| --- | --- | --- | --- |
| GET | `/public/post/{id}/comments` | Charger paresseusement les commentaires racines d'un post avec pagination et méta-données (`isLiked`, `reactions_count`). | Accès public |
| GET | `/public/comment/{id}/likes` | Lister les likes associés à un commentaire. | Accès public |
| GET | `/public/comment/{id}/reactions` | Lister les réactions associées à un commentaire. | Accès public |

### Exemple de réponse JSON pour `/public/comment/{id}/likes`

```json
{
  "commentId": "f8b1b254-a012-11ee-b962-0242ac120002",
  "likes": [
    {
      "id": "aa31d3b8-a012-11ee-b962-0242ac120002",
      "user": {
        "id": "99",
        "displayName": "Bob"
      }
    }
  ]
}
```

## Like (`/v1/like`)

| Méthode | Chemin | Description | Rôle minimum |
| --- | --- | --- | --- |
| GET | `/v1/like/count` | Compter les likes. | `ROLE_ADMIN` |
| GET | `/v1/like` | Lister les likes. | `ROLE_ADMIN` |
| GET | `/v1/like/ids` | Lister les identifiants des likes. | `ROLE_ADMIN` |
| GET | `/v1/like/{id}` | Obtenir un like spécifique (UUID v1). | `ROLE_ADMIN` |

## Statistiques (`/v1/statistics`)

| Méthode | Chemin | Description | Rôle minimum |
| --- | --- | --- | --- |
| GET | `/v1/statistics` | Agrégations mensuelles des blogs, posts, likes et commentaires, mises en cache 1h. | Accès public authentifié |

### Exemple de réponse JSON pour `/v1/statistics`

```json
{
  "postsPerMonth": {
    "2024-01": 12,
    "2024-02": 18
  },
  "blogsPerMonth": {
    "2024-01": 2
  },
  "likesPerMonth": {
    "2024-02": 250
  },
  "commentsPerMonth": {
    "2024-02": 57
  }
}
```

Ces structures correspondent aux tableaux retournés par les repositories et permettent d'afficher des métriques par mois côté client.
