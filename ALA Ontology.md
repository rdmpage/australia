# ala-bie - OntologyCompetency.wiki

This page is based on [ala-bie - OntologyCompetency.wiki](https://code.google.com/archive/p/ala-bie/wikis/OntologyCompetency.wiki), and was in turn discovered via [Overview of Competency Questions](https://marinemetadata.org/references/competencyquestionsoverview) (who credits the ALA page to David Thau).

This page is meant to collect all competency questions for ALA ontologies. Initially it will focus on a small demo application with just a few domains. Eventually there should be a page here for each separate ontology, and a page for queries that bridge specific ontologies. The numbering scheme is meant to make it simple to refer to specific questions in ontology documentation.

## Competency Questions for Demo

First step is to develop competency questions. Let's start by focusing on questions integrating the following data types:

- Taxonomic concepts and names
- Threatened fauna
- Parasite/host information

The idea at this point is to list just those questions we need to determine the competency of the ontologies for the demo. Let's focus on questions about fish. We'll use the following data sources

[http://speciesindex.org/](http://speciesindex.org/) Taxonomy information

[EPBC Act List of Threatened Fauna](http://www.environment.gov.au/cgi-bin/sprat/public/publicthreatenedlist.pl?wanted=fauna) Threatened Fauna

[AFD](http://www.environment.gov.au/biodiversity/abrs/online-resources/fauna/afd/taxa/MONOGENEA/hosts) Parasite/Host Information

Sample high level question: Give me the parasites of fish in the order Atheriniformes that are threatened according to EPBC.

## Taxonomy Questions (Ta)

Ta1: What are the within ?

Example question: what are the species in Melanotaeniidae?

Example answers: Melanotaenia eachamensis , Glossolepis dorityi

Example query: (isNameOf some (isDescendentTaxonOf some (hasName some (nameComplete value "Melanotaeniidae")))) and (rank value Species)

## Endangered Questions (En)

En1: What are the species of at risk level ?

Example question: what are the vulnerable organisms?

Example answers: Nannoperca variegata, Pristis microdon

Example query: isNameCompleteInstOf some (hasThreatenedStatus some (hasThreatenedName value Vulnerable))

## Parasitism Questions (Pa)

Pa1: What are the parasites for ?

Example question: What are the parasites of Melanotaenia eachamensis?

Example answer: Iliocirrus mazlini

Example query: has_host_organism some (nameComplete value "Melanotaenia eachamensis")

## Endangered x Taxonomy Questions (EnTa)

EnTa1: What are the <rank> in ?

Example question: What are the genera in Melanotaeniidae that have endangered species?

Example answer: Melanotaenia

Example query: isParentTaxonOf some ( hasName some (isNameOf some (isChildTaxonOf some (hasName some ((isNameOf some (isDescendentTaxonOf some (hasName some (nameComplete value "Melanotaeniidae")))) and (rank value Genus)))) and (hasThreatenedStatus some (hasThreatenedName value Endangered))))

## Parasitism x Taxonomy Questions (PaTa)

PaTa1: What are parasites for species in ?

Example question: What are the parasites for species in Melanotaeniidae?

Example answers: Iliocirrus mazlini, Helicirrus maccullochii, Helicirrus mcivori

Example query: has_host_organism some (isNameOf some (isDescendentTaxonOf some (isParentTaxonOf some ( hasName some (isNameOf some (isChildTaxonOf some (hasName some ((isNameOf some (isDescendentTaxonOf some (hasName some (nameComplete value "Melanotaeniidae")))) and (rank value Genus)))))))))

## Endangered x Parasitism (EnPa)

EnPa1: What are the parasites of species?

Example question: What are the parasites of endangered species?

Example answers: liocirrus mazlini, Pseudophyllodistomum murrayense

Example query:

has_host_organism some (hasThreatenedStatus some (hasThreatenedName value Endangered))

## Endangered x Parasitism x Taxonomy Questions (EnPaTa)

EnPaTa1: What are the parasites for ?

Example question: What are the parasites of fish in the family Melanotaeniidae that are endangered according to EPBC?

Example answers: Pretestis australianus, Lingidigitis gracilis

Example query: has_host_organism some (isNameOf some (isDescendentTaxonOf some (isParentTaxonOf some ( hasName some (isNameOf some (isChildTaxonOf some (hasName some ((isNameOf some (isDescendentTaxonOf some (hasName some (nameComplete value "Melanotaeniidae")))) and (rank value Genus)))) and (hasThreatenedStatus some (hasThreatenedName value Endangered)))))))

