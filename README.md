# SearchLight

Fuzzy search for ownCloud using PostgreSQL

## Requirements

SearchLight requires ownCloud to be setup with PostgreSQL as database backend and requires PostgreSQL to be compiled with the `pg_trgm` extension.

## Fuzzy search

SearchLight uses PostgresSQL's fuzzy search features to provide fast fuzzy matching of file names.
 
![Fuzzy matching](https://i.imgur.com/1klNa7k.png)
