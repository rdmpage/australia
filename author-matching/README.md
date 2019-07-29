# Matching author names


See also https://www.wikidata.org/wiki/Wikidata:WikiProject_Taxonomy

## Matching authors work flow

- Build local ZooBank database and local triple store (oz-zoobank)

- Get list of relevant ZooBank publication identifiers, for example from Wikidata:

```
select ?item 
where
{
  # ZooBank publications
  ?item wdt:P2007 ?zb .
  
  # From Zootaxa
  ?item wdt:P1433 ?container .
  ?container rdfs:label "Zootaxa"@en .
  
  # 2001
  ?item wdt:P577 ?publicationDate .
  FILTER (YEAR(?publicationDate) = 2001)
  
}
```
[Try it](https://w.wiki/6Uu)

- Run ```./tojsonld.php``` on that list of ZooBank identifiers, output as triples using my schema.

- Upload triples to local ZooBank triple store 

```
curl http://localhost:32775/blazegraph/namespace/alec/sparql?context-uri=https://wikidata.org -H 'Content-Type: text/rdf+n3' --data-binary '@w.nt'  --progress-bar | tee /dev/null
```

- Run ```./query.php``` to match authors in ZooBank and Wikidata using publication and authorities identifiers. This generates Quickstatements to convert author strings to things, and dumps list of authors that should have ZooBank author ids but don’t.

### Finding candidates and measuring progress

This query finds Wikispecies authors in Wikidata that are likely to be taxonomists (e.g., arachnologists) and sees which ones have ZooBank author ids in their Wikidata records.

```
#Items with a Wikispecies sitelink
#illustrates sitelink selection, ";" notation
SELECT ?item ?itemLabel ?article ?zoobank
WHERE
{
    # Wikispecies articles
	?article 	schema:about ?item ;
	schema:isPartOf <https://species.wikimedia.org/> .
    ?item wdt:P31 wd:Q5 .
  
    # only include people flagged as arachnologists
    ?item wdt:P106 wd:Q17344952 .
  
    # Do they have a ZooBank author ID?
    OPTIONAL { ?item wdt:P2006 ?zoobank . }
  
	SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en" }
}

```
[Try it](https://w.wiki/6bf)

## ZooBank

Build a local KG for ZooBank publications. For those of interest, fetch from WikiData, convert to LD, and add to KG. Query to find missing authors, see if these authors exist in Wikidata, add links to articles.

```
curl http://localhost:32774/blazegraph/namespace/alec/sparql?context-uri=https://wikidata.org -H 'Content-Type: text/rdf+n3' --data-binary '@w.nt'  --progress-bar | tee /dev/null
```

SPARQL query

SELECT *
WHERE 
{
  GRAPH <http://zoobank.org> {
  VALUES ?work { <urn:lsid:zoobank.org:pub:D7588A4E-D06E-4524-BB49-AC16C3FEC849> } .
  ?work <http://schema.org/name> ?title .
  ?work <http://schema.org/creator> ?role . 
  ?role <http://schema.org/roleName> ?roleName . 
  ?role <http://schema.org/creator> ?person . 
  ?person <http://schema.org/familyName> ?familyName .
  ?person <http://schema.org/name> ?name .

  OPTIONAL {
    ?work <http://schema.org/identifier> ?identifier .
	?identifier <http://schema.org/propertyID> "doi" .
	?identifier <http://schema.org/value> ?doi .      
   }  
} 


 
}


```
SELECT *
WHERE 
{
  GRAPH <http://zoobank.org> {
  VALUES ?work { <urn:lsid:zoobank.org:pub:233A4BBC-3BC6-4094-BA60-9DC210E4640C> } .
  ?work <http://schema.org/name> ?title .
  ?work <http://schema.org/creator> ?role . 
  ?role <http://schema.org/roleName> ?roleName . 
  ?role <http://schema.org/creator> ?person . 
  ?person <http://schema.org/familyName> ?familyName .
  ?person <http://schema.org/name> ?name .

  OPTIONAL {
    ?work <http://schema.org/identifier> ?identifier .
	?identifier <http://schema.org/propertyID> "doi" .
	?identifier <http://schema.org/value> ?doi .      
   }  
} 
        
  GRAPH <https://wikidata.org> {
    ?wiki_identifier <http://schema.org/value> "urn:lsid:zoobank.org:pub:233A4BBC-3BC6-4094-BA60-9DC210E4640C" .
    ?wiki_work <http://schema.org/identifier> ?wiki_identifier .
    
   ?wiki_work <http://schema.org/creator> ?wiki_role . 
    ?wiki_role <http://schema.org/roleName> ?wiki_roleName . 
    ?wiki_role <http://schema.org/creator> ?wiki_person . 
    ?wiki_person <http://schema.org/name> ?wiki_name .

    OPTIONAL {
      ?wiki_person <http://schema.org/identifier> ?wiki_person_identifier .
      ?wiki_person_identifier <http://schema.org/propertyID> "orcid" .
      ?wiki_person_identifier <http://schema.org/value> ?orcid .
      }
    
   OPTIONAL {
      ?wiki_person <http://schema.org/identifier> ?wiki_person_identifier .
      ?wiki_person_identifier <http://schema.org/propertyID> "zoobank" .
      ?wiki_person_identifier <http://schema.org/value> ?wiki_zoobank_person .
      }
        
    
  }

FILTER (?roleName = ?wiki_roleName)
 
}
ORDER BY ?roleName



```


## Taxonomists working on Australian taxa

Map ZooBank refs to AFD, add AFD to Wikidata, query authors with AFD, find identifiers for those that are lack them.


```
curl http://localhost:32775/blazegraph/namespace/alec/sparql?context-uri=https://wikidata.org -H 'Content-Type: text/rdf+n3' --data-binary '@w.nt'  --progress-bar | tee /dev/null
```


### Identifiers for people who publish on Australian taxonomy

```
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT * WHERE
{ ?work wdt:P6982 ?afd .
  
  {
    ?work wdt:P50 ?author .
    ?author rdfs:label ?name .
    FILTER (lang(?name) = 'en')
    
  OPTIONAL {
    ?author wdt:P496 ?orcid .
   }
  }
  UNION
    {
      ?work wdt:P2093 ?name .
  }
   
}
```

## Details

```
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT DISTINCT ?author ?name ?orcid ?gender_label ?citizenship_label WHERE
{ ?work wdt:P6982 ?afd .
  
  
    ?work wdt:P50 ?author .
    ?author rdfs:label ?name .
    FILTER (lang(?name) = 'en')
    
  OPTIONAL {
    ?author wdt:P496 ?orcid .
   }
  
  OPTIONAL {
    ?author wdt:P21 ?gender .
    ?gender rdfs:label ?gender_label .
    FILTER (lang(?gender_label) = 'en')    
   }
 
   OPTIONAL {
    ?author wdt:P27 ?citizenship .
    ?citizenship rdfs:label ?citizenship_label .
    FILTER (lang(?citizenship_label) = 'en')    
   }
   
}
```

### Bubble chart of known nationalities of people publishing on Australian taxonomy

```
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT DISTINCT 
?citizenship ?citizenship_label (COUNT(?citizenship_label) AS ?count) 
WHERE
{ ?work wdt:P6982 ?afd .
  
  
    ?work wdt:P50 ?author .
    ?author rdfs:label ?name .
    FILTER (lang(?name) = 'en')
     ?author wdt:P27 ?citizenship .
    ?citizenship rdfs:label ?citizenship_label .
    FILTER (lang(?citizenship_label) = 'en')    
   
   
}
GROUP BY ?citizenship ?citizenship_label


```
[Try it](https://w.wiki/6PX)

### Gender ratio

```
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT DISTINCT 
#?author ?name ?orcid ?gender_label 
?gender ?gender_label (COUNT(?gender_label) AS ?count) 
WHERE
{ ?work wdt:P6982 ?afd .
  
  
    ?work wdt:P50 ?author .
    ?author rdfs:label ?name .
    FILTER (lang(?name) = 'en')
 
  
   {
    ?author wdt:P21 ?gender .
    ?gender rdfs:label ?gender_label .
    FILTER (lang(?gender_label) = 'en')    
   }
 
  
   
}
GROUP BY ?gender ?gender_label
```
[Try it](https://w.wiki/6Pa)

### Authors in AFD with ORCIDs

```
SELECT DISTINCT ?author ?name (IRI(CONCAT("https://orcid.org/",?orcid)) AS ?orcid_url) WHERE
{ 
  ?work wdt:P6982 ?afd .
  {
    ?work wdt:P50 ?author .
    ?author rdfs:label ?name .
    FILTER (lang(?name) = 'en')
    
   ?author wdt:P496 ?orcid .
  }
}
ORDER BY ?name
```
[Try it](https://w.wiki/6EP)


### Birth dates

Note that major problem is birth dates are often not precise, and a date of “20th C” gets translated as 1 January 2000, which is no use.

```
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT DISTINCT 
?author ?name 
(YEAR(?birth) AS ?year)
WHERE
{ ?work wdt:P6982 ?afd .
  
  
    ?work wdt:P50 ?author .
    ?author rdfs:label ?name .
    FILTER (lang(?name) = 'en')
 
 {
    ?author wdt:P496 ?orcid .
   }
  
    {
    ?author wdt:P569 ?birth .
   }
   
}
#GROUP BY ?citizenship ?citizenship_label
ORDER BY ?year
```

[Try it](https://w.wiki/6Pn)









