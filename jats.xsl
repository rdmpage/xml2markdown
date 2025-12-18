<?xml version='1.0' encoding='utf-8'?>
<xsl:stylesheet version='1.0' xmlns:xsl='http://www.w3.org/1999/XSL/Transform' xmlns:xlink='http://www.w3.org/1999/xlink' xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tp="http://www.plazi.org/taxpub">
  <xsl:output method='text' version='1.0' encoding='utf-8'  />
  <xsl:strip-space elements="*"/>
  
  <!--
<xsl:template match="text()">
  <xsl:value-of select="concat('[', ., ']')"/>
</xsl:template>  
-->

<xsl:template match="text()">
  <xsl:value-of select="normalize-space(.)"/>
  <!-- <xsl:text> </xsl:text>-->
</xsl:template>
  
  <!-- ChatGPT start -->
  <xsl:template name="colcount">
    <xsl:param name="cells" />
    <xsl:param name="sum" select="0" />
    <xsl:choose>
      <xsl:when test="not($cells)">
        <xsl:value-of select="$sum" />
      </xsl:when>
      <xsl:otherwise>
        <xsl:variable name="span">
          <xsl:choose>
            <xsl:when test="$cells[1]/@colspan">
              <xsl:value-of select="number($cells[1]/@colspan)" />
            </xsl:when>
            <xsl:otherwise>1</xsl:otherwise>
          </xsl:choose>
        </xsl:variable>
        <xsl:call-template name="colcount">
          <xsl:with-param name="cells" select="$cells[position() &gt; 1]" />
          <xsl:with-param name="sum" select="$sum + number($span)" />
        </xsl:call-template>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <xsl:template name="separator">
    <xsl:param name="n" />
    <xsl:if test="$n &gt; 0">
      <xsl:text> --- |</xsl:text>
      <xsl:call-template name="separator">
        <xsl:with-param name="n" select="$n - 1" />
      </xsl:call-template>
    </xsl:if>
  </xsl:template>
  <!-- ChatGPT end -->
  <xsl:template match="/">
        <xsl:apply-templates select="//article-meta" />
        <xsl:apply-templates select="//abstract" />
        <!-- scan specific stuff -->
        <xsl:apply-templates select="supplementary-material" />
        <xsl:apply-templates select="//body" />
        <xsl:apply-templates select="//back" />
        <!-- Biodiversity Data Journal -->
        <xsl:apply-templates select="//floats-group" />
  </xsl:template>
  <xsl:template match="//article-meta">
    <!-- why do we have different ways of doing this? -->
    <xsl:value-of select="//journal-meta/journal-title-group/journal-title" />
    <xsl:value-of select="../journal-meta/journal-title" />
    <xsl:text> </xsl:text>
    <xsl:if test="//article-meta/pub-date/day">
      <xsl:value-of select="//article-meta/pub-date/day" />
      <xsl:text> </xsl:text>
    </xsl:if>
    <xsl:if test="//article-meta/pub-date/month">
      <xsl:choose>
        <xsl:when test="//article-meta/pub-date/month = 1">
          <xsl:text>January</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 2">
          <xsl:text>February</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 3">
          <xsl:text>March</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 4">
          <xsl:text>April</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 5">
          <xsl:text>May</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 6">
          <xsl:text>June</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 7">
          <xsl:text>July</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 8">
          <xsl:text>August</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 9">
          <xsl:text>September</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 10">
          <xsl:text>October</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 11">
          <xsl:text>November</xsl:text>
        </xsl:when>
        <xsl:when test="//article-meta/pub-date/month = 12">
          <xsl:text>December</xsl:text>
        </xsl:when>
      </xsl:choose>
      <xsl:text> </xsl:text>
    </xsl:if>
    <xsl:value-of select="//article-meta/pub-date/year" />
    <xsl:text> </xsl:text>
    <xsl:value-of select="//article-meta/volume" />
    <xsl:if test="//article-meta/issue">
      <xsl:text>(</xsl:text>
      <xsl:value-of select="//article-meta/issue" />
      <xsl:text>)</xsl:text>
    </xsl:if>
    <xsl:text>: </xsl:text>
    <xsl:if test="//article-meta/fpage">
      <xsl:value-of select="//article-meta/fpage" />
      <xsl:text>-</xsl:text>
      <xsl:value-of select="//article-meta/lpage" />
    </xsl:if>
    <xsl:if test="//article-meta/elocation-id">
      <xsl:value-of select="//article-meta/elocation-id" />
    </xsl:if>
    <xsl:text>&#xa;</xsl:text>
    <xsl:text># </xsl:text>
    <xsl:apply-templates select="//article-meta/title-group/article-title" />
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates select="//contrib-group" />
    <xsl:apply-templates select="//article-id" />
    <xsl:apply-templates select="//self-uri[@content-type='lsid']" />
  </xsl:template>
  <xsl:template match="article-id">
    <xsl:choose>
      <xsl:when test="@pub-id-type='doi'">
        <xsl:text>- </xsl:text>
        <xsl:text>DOI:</xsl:text>
        <xsl:value-of select="." />
        <xsl:text>&#xa;</xsl:text>
      </xsl:when>
      <xsl:when test="@pub-id-type='pmid'">
        <xsl:text>- </xsl:text>
        <xsl:text>PMID:</xsl:text>
        <xsl:value-of select="." />
        <xsl:text>&#xa;</xsl:text>
      </xsl:when>
      <xsl:when test="@pub-id-type='pmc'">
        <xsl:text>- </xsl:text>
        <xsl:text>PMC</xsl:text>
        <xsl:value-of select="." />
        <xsl:text>&#xa;</xsl:text>
      </xsl:when>
      <xsl:otherwise />
    </xsl:choose>
  </xsl:template>
  <!-- ZooBank LSID for article -->
  <xsl:template match="//self-uri[@content-type='lsid']">
    <xsl:text>- </xsl:text>
    <xsl:value-of select="." />
  </xsl:template>
  <!-- authors -->
  <xsl:template match="//contrib-group">
    <xsl:apply-templates select="contrib[@contrib-type='author']" />
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <!-- need to include only authors -->
  <xsl:template match="contrib[@contrib-type='author']">
    <xsl:if test="position() != 1">
      <xsl:choose>
        <xsl:when test="position() = last()">
          <xsl:text> and </xsl:text>
        </xsl:when>
        <xsl:otherwise>
          <xsl:text>, </xsl:text>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:if>
    <xsl:value-of select="name/given-names" />
    <xsl:text> </xsl:text>
    <xsl:value-of select="name/surname" />
  </xsl:template>
  <xsl:template match="//abstract">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="//body">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="//back">
    <xsl:apply-templates select="ack" />
    <xsl:apply-templates select="ref-list" />
  </xsl:template>
  <xsl:template match="sec">
    <xsl:apply-templates />
  </xsl:template>
  <!-- basic elements -->
  <xsl:template match="p">
     <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <xsl:template match="italic">
  <xsl:text> _</xsl:text>
  <xsl:apply-templates />
  <xsl:text>_ </xsl:text>
  </xsl:template>
  <xsl:template match="bold">
  <xsl:text>**</xsl:text>
  <xsl:apply-templates />
  <xsl:text>**</xsl:text>
  </xsl:template>
  <xsl:template match="list">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="list-item">
    <xsl:text>- </xsl:text>
    <xsl:if test="label">
      <xsl:value-of select="label" />
    </xsl:if>
    <xsl:if test="p">
      <xsl:value-of select="p" />
    </xsl:if>
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <!-- citations -->
  <xsl:template match="xref">
    <xsl:choose>
      <xsl:when test="@ref-type='bibr'">
        <a>
          <xsl:attribute name="href">
            <xsl:text>#</xsl:text>
            <xsl:value-of select="@rid" />
          </xsl:attribute>
          <xsl:apply-templates />
        </a>
      </xsl:when>
      <xsl:otherwise>
        <xsl:apply-templates />
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- links to data -->
  <xsl:template match="ext-link">
    <xsl:choose>
      <xsl:when test="ext-link-type='gen'">
        <!-- <span style="background-color:blue;color:white;"> -->
        <xsl:apply-templates />
        <!-- </span> -->
      </xsl:when>
      <xsl:otherwise>
        <!-- <span style="background-color:green;color:white;"> -->
        <xsl:apply-templates />
        <!-- </span> -->
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- named content (typically special markup added by Pensoft)-->
  <xsl:template match="named-content">
    <xsl:choose>
      <xsl:when test="@content-type='taxon-name'">
        <!-- <xsl:if test="@xlink:href"> 
                                        <span style="background-color:blue;color:white;">
                                                <xsl:value-of select="@xlink:href" />
                                        </span>
                        </xsl:if> -->
        <!-- <span style="background-color:orange;"> -->
        <xsl:apply-templates />
        <!-- </span> -->
      </xsl:when>
      <xsl:when test="@content-type='taxon-authority'">
        <!-- <span style="background-color:pink;color:white;"> -->
        <xsl:apply-templates />
        <!-- </span> -->
      </xsl:when>
      <xsl:when test="@content-type='taxon-status'">
        <!-- <span style="background-color:red;color:white;"> -->
        <xsl:apply-templates />
        <!-- </span> -->
      </xsl:when>
      <xsl:when test="@content-type='dwc:verbatimCoordinates'">
        <!-- <span style="background-color:green;color:white;"> -->
        <xsl:apply-templates />
        <!-- </span> -->
      </xsl:when>
      <xsl:when test="@content-type='comment'">
        <!-- <span style="background-color:#CCCCCC;"> -->
        <xsl:apply-templates />
        <!-- </span> -->
      </xsl:when>
      <xsl:otherwise>
        <!-- <span style="background-color:yellow;"> -->
        <span>
          <xsl:apply-templates />
        </span>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- label -->
  <xsl:template match="label">
    <xsl:if test="normalize-space(.)">
      <xsl:text>**</xsl:text>
	  <xsl:value-of select="normalize-space()"/>
      <xsl:text>**</xsl:text>
    </xsl:if>
  </xsl:template>
  <!-- title -->
  <xsl:template match="sec/title">
    <!-- heading level based on depth of sec tag -->
    <xsl:choose>
      <xsl:when test="count(ancestor-or-self::sec)=1">
        <xsl:text>&#xa;</xsl:text>
        <xsl:text>## </xsl:text>
        <xsl:apply-templates />
        <xsl:text>&#xa;</xsl:text>
      </xsl:when>
      <xsl:when test="count(ancestor-or-self::sec)=2">
        <xsl:text>&#xa;</xsl:text>
        <xsl:text>### </xsl:text>
        <xsl:apply-templates />
        <xsl:text>&#xa;</xsl:text>
      </xsl:when>
      <xsl:when test="count(ancestor-or-self::sec)=3">
        <xsl:text>&#xa;</xsl:text>
        <xsl:text>#### </xsl:text>
        <xsl:apply-templates />
        <xsl:text>&#xa;</xsl:text>
      </xsl:when>
      <xsl:otherwise>
        <xsl:text>&#xa;</xsl:text>
        <xsl:text>#### </xsl:text>
        <xsl:apply-templates />
        <xsl:text>&#xa;</xsl:text>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- <xsl:template match="ack/title"><h2><xsl:apply-templates /></h2></xsl:template> -->
  <xsl:template match="title">
  <xsl:text>**</xsl:text>
  <xsl:apply-templates />
  <xsl:text>**</xsl:text></xsl:template>
  <!-- table -->
  <xsl:template match="table-wrap">
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="table">
    <xsl:text>&#xa;</xsl:text>
    <!-- first row in the table (thead first, else tbody) -->
    <xsl:variable name="firstRow" select="(thead/tr | tbody/tr | tr)[1]" />
    <xsl:variable name="cols">
      <xsl:call-template name="colcount">
        <xsl:with-param name="cells" select="$firstRow/*[self::th or self::td]" />
      </xsl:call-template>
    </xsl:variable>
    <xsl:apply-templates select="thead" />
    <!-- output separator line -->
    <xsl:text>| </xsl:text>
    <xsl:call-template name="separator">
      <xsl:with-param name="n" select="number($cols)" />
    </xsl:call-template>
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates select="tbody" />
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <xsl:template match="thead">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="tbody">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="tr">
    <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <xsl:template match="th">
    <xsl:if test="not(preceding-sibling::th)">
      <xsl:text>| </xsl:text>
    </xsl:if>
    <xsl:apply-templates />
    <xsl:text> |</xsl:text>
  </xsl:template>
  <xsl:template match="td">
    <xsl:if test="not(preceding-sibling::td)">
      <xsl:text>| </xsl:text>
    </xsl:if>
    <xsl:apply-templates />
    <xsl:text> |</xsl:text>
  </xsl:template>
  <!-- tp -->
  <!--
          <tp:nomenclature>
            <tp:taxon-name><tp:taxon-name-part taxon-name-part-type="genus">Parasthena</tp:taxon-name-part> (<tp:taxon-name-part taxon-name-part-type="subgenus">Parasthena</tp:taxon-name-part>) <tp:taxon-name-part taxon-name-part-type="species">flexilinea</tp:taxon-name-part></tp:taxon-name>
            <tp:taxon-authority>Warren, 1902</tp:taxon-authority>
            <tp:nomenclature-citation-list>
              <tp:nomenclature-citation>
                <tp:taxon-name><tp:taxon-name-part taxon-name-part-type="genus">Parasthena</tp:taxon-name-part> (<tp:taxon-name-part taxon-name-part-type="subgenus">Parasthena</tp:taxon-name-part>) <tp:taxon-name-part taxon-name-part-type="species">flexilinea</tp:taxon-name-part></tp:taxon-name>
                <comment>
                  <xref ref-type="bibr" rid="B1615067">Warren 1902</xref>
                </comment>
              </tp:nomenclature-citation>
            </tp:nomenclature-citation-list>
          </tp:nomenclature>
        -->
  <xsl:template match="tp:nomenclature">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="tp:taxon-name">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="tp:taxon-authority">
    <xsl:apply-templates />
  </xsl:template>
  <xsl:template match="tp:nomenclature-citation-list">
    <ul>
      <xsl:apply-templates />
    </ul>
  </xsl:template>
  <xsl:template match="tp:nomenclature-citation-list/tp:taxon-authority">
    <li>
      <xsl:apply-templates />
    </li>
  </xsl:template>
  <xsl:template match="tp:taxon-name-part">
    <xsl:choose>
      <xsl:when test="@taxon-name-part-type='genus'">
        <i>
          <xsl:value-of select="." />
        </i>
      </xsl:when>
      <xsl:when test="@taxon-name-part-type='subgenus'">
        <i>
          <xsl:value-of select="." />
        </i>
      </xsl:when>
      <xsl:when test="@taxon-name-part-type='species'">
        <i>
          <xsl:value-of select="." />
        </i>
      </xsl:when>
      <xsl:when test="@taxon-name-part-type='family'">
        <font style="font-variant: small-caps">
          <xsl:value-of select="." />
        </font>
      </xsl:when>
      <xsl:when test="@taxon-name-part-type='subfamily'">
        <font style="font-variant: small-caps">
          <xsl:value-of select="." />
        </font>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="." />
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- figure -->
  <xsl:template match="fig">
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>![]</xsl:text>
    <xsl:text>(</xsl:text>
    <!--            <xsl:text>https://journals.plos.org/plosone/article/figure/image?size=large&amp;id=</xsl:text> 
                <xsl:value-of select="graphic/@xlink:href" /> -->
    <xsl:choose>
      <!-- PLoS -->
      <xsl:when test="contains(graphic/@xlink:href, 'journal.pone')">
        <xsl:value-of select="concat('https://journals.plos.org/plosone/article/figure/image?size=large&amp;id=', graphic/@xlink:href)" />
      </xsl:when>

      <!-- Wellcome -->
      <xsl:when test="contains(graphic/@xlink:href, 'wellcomeopenresearch.s3.eu-west-1.amazonaws.com')">
        <xsl:value-of select="concat( substring-before(graphic/@xlink:href, 'wellcomeopenresearch.s3.eu-west-1.amazonaws.com'), 'wellcomeopenresearch-files.f1000.com', substring-after(graphic/@xlink:href, 'wellcomeopenresearch.s3.eu-west-1.amazonaws.com') )" />
      </xsl:when>
 
      <!-- Pensoft -->
      <xsl:when test="contains(graphic/@xlink:href, 'ZooKeys')">
        <xsl:value-of select="graphic/uri" />
      </xsl:when>
      
      <!-- PMC -->
      <xsl:when test="//article-id[@pub-id-type='pmc']">
        <xsl:value-of select="concat('PMC', //article-id[@pub-id-type='pmc'], '/', graphic/@xlink:href, '.jpg')" /> 
      </xsl:when>
      
      <xsl:otherwise>
       <xsl:value-of select="graphic/@xlink:href" />
      </xsl:otherwise>
    </xsl:choose>
    <xsl:text>)</xsl:text>
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <!-- references -->
  <xsl:template match="ref-list">
    <xsl:text>## </xsl:text>
    <xsl:value-of select="title" />
    <xsl:text>&#xa;</xsl:text>
    <!-- Kew JATS is broken and has ref-list twice(!) -->
    <xsl:apply-templates />
  </xsl:template>
  <!-- Reference list -->
  <xsl:template match="ref">
    <xsl:text>- </xsl:text>
    <a>
      <xsl:attribute name="name">
        <xsl:value-of select="@id" />
      </xsl:attribute>
    </a>
    <xsl:apply-templates select="mixed-citation" />
    <!-- Hindawi -->
    <xsl:apply-templates select="nlm-citation" />
    <!-- Biodiversity Data Journal -->
    <xsl:apply-templates select="element-citation" />
    <!-- Frontiers  -->
    <xsl:apply-templates select="citation" />
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <!-- authors -->
  <xsl:template match="//person-group">
    <xsl:apply-templates select="name" />
  </xsl:template>
  <xsl:template match="name">
    <xsl:if test="position() != 1">
      <xsl:text>, </xsl:text>
    </xsl:if>
    <xsl:value-of select="surname" />
    <xsl:text>, </xsl:text>
    <xsl:value-of select="given-names" />
  </xsl:template>
  <xsl:template match="article-title">
    <xsl:text>**</xsl:text>
    <xsl:apply-templates />
    <xsl:text>**</xsl:text>
  </xsl:template>
  <xsl:template match="chapter-title">
    <xsl:text>**</xsl:text>
    <xsl:value-of select="." />
    <xsl:text>**</xsl:text>
  </xsl:template>
  <xsl:template match="source">
    <xsl:apply-templates />
    <xsl:text>, </xsl:text>
  </xsl:template>
  <xsl:template match="volume">
    <xsl:value-of select="." />
    <xsl:text>: </xsl:text>
  </xsl:template>
  <xsl:template match="fpage">
    <xsl:value-of select="." />
  </xsl:template>
  <xsl:template match="lpage">
    <xsl:text>-</xsl:text>
    <xsl:value-of select="." />
  </xsl:template>
  <xsl:template match="publisher-name">
    <xsl:value-of select="." />
  </xsl:template>
  <xsl:template match="publisher-loc">
    <xsl:value-of select="." />
  </xsl:template>
  <xsl:template match="size">
    <xsl:value-of select="." />
  </xsl:template>
  <xsl:template match="year">
    <!-- <xsl:text> (</xsl:text> -->
    <xsl:value-of select="." />
    <!-- <xsl:text>) </xsl:text> -->
  </xsl:template>
  <!-- a citation -->
  <xsl:template match="mixed-citation | element-citation | nlm-citation | citation">
    <xsl:apply-templates />
    <!-- links -->
    <xsl:for-each select="uri">
      <xsl:choose>
        <xsl:when test="@xlink:type='simple'">
          <xsl:value-of select="." />
        </xsl:when>
        <xsl:otherwise></xsl:otherwise>
      </xsl:choose>
    </xsl:for-each>
    <!-- identifiers -->
    <!--
                <xsl:for-each select="ext-link">
                        <xsl:choose>
                                <xsl:when test="@ext-link-type='uri'">
                                                <xsl:value-of select="." />
                                </xsl:when>
                                <xsl:when test="@ext-link-type='doi'">
                                                <xsl:text> DOI:</xsl:text>
                                                <xsl:value-of select="." />
                                </xsl:when>
                                
                                <xsl:otherwise>
                                </xsl:otherwise>
                        </xsl:choose>
                </xsl:for-each>
                -->
    <!--
                <xsl:for-each select="pub-id">
                        <xsl:choose>
                                <xsl:when test="@pub-id-type='pmid'">
                                                <xsl:text> PMID:</xsl:text>
                                                <xsl:value-of select="." />
                                </xsl:when>
                                <xsl:when test="@pub-id-type='doi'">
                                                <xsl:text> DOI:</xsl:text>
                                                <xsl:value-of select="." />
                                </xsl:when>
                                
                                <xsl:otherwise>
                                </xsl:otherwise>
                        </xsl:choose>
                </xsl:for-each>
                -->
  </xsl:template>
  <!-- 10.3897/phytokeys.61.7590 acknowledgements have def/p -->
  <xsl:template match="def/p">
    <xsl:value-of select="." />
  </xsl:template>
  <!-- eat this so it doesn't appear in figure captions-->
  <xsl:template match="object-id"></xsl:template>
  <!-- eat this so it doesn't appear in figure captions-->
  <xsl:template match="uri"></xsl:template>
</xsl:stylesheet>
