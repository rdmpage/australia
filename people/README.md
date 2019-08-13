# People database


## Find people by first letter of last name

Use regular expression parse the name string (crude). In this case search is for authors in AFD that have ORCIDs and whose last name starts with ‘A’

```
SELECT DISTINCT ?author ?name (IRI(CONCAT("https://orcid.org/",?orcid)) AS ?orcid_url) ?rg WHERE
{ 
  ?work wdt:P6982 ?afd .
  {
    ?work wdt:P50 ?author .
    ?author rdfs:label ?name .
    FILTER (lang(?name) = 'en') .
    
    # Filter on those with last name begining with 'A'
    FILTER (regex(str(?name), "\\s+A\\w+(-\\w+)?$")) .
    
    ?author wdt:P496 ?orcid .
    
    OPTIONAL {
      ?author wdt:P2038 ?rg .
     }
  }
}
ORDER BY ?name
```

[Try it](https://w.wiki/6jY)

## Images

Can get these from ResearchGate, which has embedded schame.org metadata.

Also CSIRO has it’s own metadata schema and includes images, e.g. https://people.csiro.au/A/A/Alan-Andersen.aspx


