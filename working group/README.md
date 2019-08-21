# DOI working group

## Definitions
- DOI
- article-level metadata
- Unpaywall
- publisher - commercial publisher, or society or other body that holds rights to content



## BHL minting identifiers for content

- BHL should only mint DOIs for articles where (a) the content is orphaned, or (b) there is explicit agreement with an existing publisher/society/etc.
- DOIs should be brand neutral so that they can be reused by publishers

### Publisher digitises back catalogue - what happens to BHL-minted DOIs?
Imagine BHL has scanned a journal, identified articles, and (with publisher’s permission) minted DOIs for those articles. At a later date the publisher decides to host those articles on it’s own web site. It will then take control of the BHL DOIs (which is why they should be brand neutral).

### Multiple DOIs

#### Same article, different registration agencies

Randall, J. E., & McCarthy, L. J. (1989). Solea stanalandi, a new sole from the Persian Gulf. Japanese Journal of Ichthyology, 36(2), 196–199.

DOI | Agency
-- | --
http://doi.org/ra/10.11369/jji1950.36.196 | JaLC
http://doi.org/ra/10.1007/BF02914322 | CrossRef

[Multiple DOIs for the same article issued by different publishers](http://iphylo.blogspot.com/2013/05/duplicate-dois-for-same-article-issued.html)

#### Same article, same agency

Systematics, biogeography and host plant associations of the Pseudomyrmex viduus group (Hymenoptera: Formicidae), Triplaris-and Tachigali-inhabiting ants

https://doi.org/10.1006/zjls.1998.0158
https://doi.org/10.1111/j.1096-3642.1999.tb00157.x

Using http://hdl.handle.net 10.1006/zjls.1998.0158 points to https://academic.oup.com/zoolinnean/article-lookup/doi/10.1111/j.1096-3642.1999.tb00157.x

If go to http://hdl.handle.net and enter the DOI 10.1006/zjls.1998.0158 and check `Don't Redirect to URLs` and `Don't Follow Aliases` we see that 10.1006/zjls.1998.0158 is an alias of 10.1111/j.1096-3642.1999.tb00157.x

Index	| Type | Timestamp | Data
-- | -- |-- |-- 
100	|HS_ADMIN	|2017-01-04 19:16:49Z|	handle=0.na/10.1093; index=200; [delete hdl,read val,modify val,del val,add val,modify admin,del admin,add admin,list]
1	|URL|	2003-08-12 15:43:12Z	|http://linkinghub.elsevier.com/retrieve/pii/S0024408298901583
700050|	700050	|2003-08-12 15:43:16Z	|20030811104844000
1970	|HS_ALIAS	|2014-11-03 19:07:33Z	|10.1111/j.1096-3642.1999.tb00157.x




[Duplicate DOIs for the same article: alias DOIs, who knew?](http://iphylo.blogspot.com/2011/09/duplicate-dois-for-same-article-alias.html)

## Using existing DOIs

- Wherever an article in BHL has a DOI (e.g., minted by a publisher) then BHL MUST include that DOI in its own article-level metadata. (This enables Unpaywall or similar service to map paywall publication to free version).


## Contributors hosting their own content

Advice for contributors hosting their own content

- make sure to include meta tags in your HTML that include the DOI (e.g., Google Scholar tags). This helps your content being indexed and discovered. Bad example http://museum.wa.gov.au/research/records-supplements/records/taxonomic-status-ricefield-rat-rattus-argentiventer-robinson-an


## Out of scope but important

Should Australian taxonomic journals merge their output into a single platform, but keep separate journal identities?