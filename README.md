# SearchLight

Fuzzy search for Nextcloud/ownCloud using PostgreSQL

## Requirements

SearchLight requires Nextcloud/ownCloud to be setup with PostgreSQL as database backend and requires PostgreSQL to be compiled with the `pg_trgm` extension.

## Installation

Due to permission limitations SearchLight can't enable the `pg_trgm` extension automatically, to enable this extension run

```
CREATE EXTENSION pg_trgm
```

On the PostgreSQL server as a super user before enabling the app.

## Fuzzy search

SearchLight uses PostgresSQL's fuzzy search features to provide fast fuzzy matching of file names.
 
![Fuzzy matching](https://i.imgur.com/1klNa7k.png)
