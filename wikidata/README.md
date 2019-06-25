# Wikidata articles and authors

## Gotchas

In some cases we need to URL encode the Wikispecies URL in order to get a hit in Wikidata :(.

## Wikidata examples

[Mark Stephen Harvey](https://tools.wmflabs.org/scholia/author/Q3294240)

## Get authors

Get list of authors of taxonomic works

```
PREFIX schema: <http://schema.org/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
SELECT DISTINCT ?person #?familyName ?name 
WHERE
{
  # Publication of taxonomic names
  ?taxonName <http://rs.tdwg.org/ontology/voc/Common#publishedInCitation> ?work .
  ?work schema:datePublished ?datePublished .
  ?work schema:name ?title .
  
  ?work schema:creator ?role  .
  ?role schema:creator ?person .
  ?person schema:name ?name .
  ?person schema:familyName ?familyName .
  
  FILTER (xsd:integer(?datePublished) >= 2010)
}
```


