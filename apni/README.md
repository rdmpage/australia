# ALA

In ALA an identifiers of the form http://id.biodiversity.org.au/node/apni/ is a **taxon**, e.g. https://bie.ala.org.au/species/http://id.biodiversity.org.au/node/apni/2920348 (*Caldesia acanthocarpa* (F.Muell.) Buchenau). The JSON form is available from https://bie-ws.ala.org.au/ws/species/http://id.biodiversity.org.au/node/apni/2920348.json.

```
…
guid: "http://id.biodiversity.org.au/node/apni/2920348",
nameAccordingToID: "http://id.biodiversity.org.au/reference/apni/42942",
taxonConceptID: "http://id.biodiversity.org.au/instance/apni/616818",
scientificNameID: "http://id.biodiversity.org.au/name/apni/94717"
…
```

The classification is a series of `node/apni/xxxx` guids which point to nodes in the current data “tree”, which is how NSL records versioned data (see https://biodiversity.org.au/nsl/docs/main.html#tree-structure-v1-0). The current taxon concept is one of these nodes.

The scientificNameID is a taxonomic name.

The taxonConceptID is the id of an instance (combination of reference and name = taxon name usage) that represents the current concept.

## APNI in GBIF

GBIF records from Australia may include the guid, e.g. http://id.biodiversity.org.au/node/apni/2920348 for *Caldesia acanthocarpa* , see https://www.gbif.org/occurrence/995840195

## Get APNI name

To get 

Header | Value
--- | ---
User-Agent | Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405
Agent | application/json

URL: http://id.biodiversity.org.au/name/apni/94717

The instances are citations of this name, linked to references.


## Get APNI names and APC taxa in bulk

https://biodiversity.org.au/nsl/services/export/index







## ALA link for Albidella acanthocarpa

https://bie.ala.org.au/species/http://id.biodiversity.org.au/name/apni/9128900

JSON link for ALA link

https://bie-ws.ala.org.au/ws/species/http://id.biodiversity.org.au/name/apni/9128900.json

guid == scientificNameID (WTF!?)

- instances
- references
- names




## ALA for animals (AFD)

https://bie-ws.ala.org.au/ws/species/urn:lsid:biodiversity.org.au:afd.taxon:b43ea8d4-bf8e-416d-9d46-11114de96761.json

taxonConceptID: "urn:lsid:biodiversity.org.au:afd.taxon:b43ea8d4-bf8e-416d-9d46-11114de96761",
scientificNameID: "urn:lsid:biodiversity.org.au:afd.name:332226"

guid = taxonConceptID