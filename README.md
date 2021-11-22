# sqlotron

Ce dépôt est juste un POC : c'est un parseur SQL minimaliste.

- il ne parse que les `SELECT`
- il échoue si la requête ne commence pas par `SELECT` (requêtes utilisant les
  CTE notamment).
- il échoue sur les requêtes avec `UNION`
- il ne parse pas les sous-requêtes, mais les détecte (y compris si imbriquées)
  pour ne pas les confondre avec la requête principale
- il détecte les chaînes de caractères classiques, y compris celles contenant le
  caractère délimiteur échappé ou répété deux fois, mais pourrait être mis en
  échec par des syntaxes plus exotiques.
- idem pour les nombres (je n'ai pas testé les nombres en représentation
  exponentielle par exemple).
- il n'a pas de tests unitaires (pas encore) et n'a pas été convenablement testé:
  pour le moment, c'est expérimental.

## Objectif

L’objectif à court terme serait d’avoir quelque chose de suffisamment fiable pour
être intégré à Dolibarr.

## État actuel

La démo contient une requête bidon :

```SQL
SELECT f.rowid, SUM(f.amount), """hello world\"()" AS teststring, ( SELECT e.name FROM llx_entity e WHERE e.rowid = f.entity) AS entity_name FROM llx_facture f LEFT JOIN `llx_societe` s ON f.fk_soc = s.rowid WHERE f.rowid IN (SELECT fk_facture FROM llx_facturedet fdet WHERE fdet.total_ttc > 1000) AND 'tutu' == "tutu" ORDER BY f.rowid ASC LIMIT 25 OFFSET 12;
```

Et montre que le parseur réussit à la décomposer ainsi (converti en JSON pour la lisibilité) :
```json
{
    "select": "SELECT f.rowid, SUM(f.amount), \"\"\"hello world\\\"()\" AS teststring, ( SELECT e.name FROM llx_entity e WHERE e.rowid = f.entity) AS entity_name ",
    "from": "FROM llx_facture f LEFT JOIN `llx_societe` s ON f.fk_soc = s.rowid ",
    "where": "WHERE f.rowid IN (SELECT fk_facture FROM llx_facturedet fdet WHERE fdet.total_ttc > 1000) AND 'tutu' == \"tutu\" ",
    "having": "",
    "orderby": "ORDER BY f.rowid ASC ",
    "limit": "LIMIT 25 ",
    "offset": "OFFSET 12;"
}
```

Ce qui permet d'ajouter facilement de nouveaux éléments (nouveaux champs sélectionnés, nouvelles jointures, nouveaux filtres, etc.).



