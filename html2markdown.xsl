<?xml version='1.0' encoding='utf-8'?>
<xsl:stylesheet version='1.0' xmlns:xsl='http://www.w3.org/1999/XSL/Transform' xmlns:xlink='http://www.w3.org/1999/xlink' xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tp="http://www.plazi.org/taxpub">
  <xsl:output method='text' version='1.0' encoding='utf-8'  />
  
<xsl:strip-space elements="*"/>

  <xsl:template match="body">
    <xsl:apply-templates />
  </xsl:template>
  
<xsl:template match="text()">
  <!-- Collapse runs of internal whitespace, but keep a single leading/trailing
       space if the original text node had one. This prevents word-joining
       artefacts like "familyOecophoridae" or "21stcentury". A whitespace-only
       text node (kept by xsl:preserve-space) collapses to a single space so
       that "Denisia cryptica" stays separated. -->
  <xsl:variable name="n" select="normalize-space(.)"/>
  <xsl:choose>
    <xsl:when test="$n = ''">
      <xsl:text> </xsl:text>
    </xsl:when>
    <xsl:otherwise>
      <xsl:if test="translate(substring(., 1, 1), ' &#9;&#10;&#13;', '') = ''">
        <xsl:text> </xsl:text>
      </xsl:if>
      <xsl:value-of select="$n"/>
      <xsl:if test="translate(substring(., string-length(.)), ' &#9;&#10;&#13;', '') = ''">
        <xsl:text> </xsl:text>
      </xsl:if>
    </xsl:otherwise>
  </xsl:choose>
</xsl:template>
  


  <!-- basic elements -->
  <xsl:template match="p">
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

  <xsl:template match="i">
  <!-- Strip edge whitespace inside the markers so "_ text _" (which markdown
       won't italicise) becomes "_text_". Any surrounding spacing comes from
       neighbouring text nodes, not from inside the emphasis. -->
  <xsl:variable name="content"><xsl:apply-templates /></xsl:variable>
  <xsl:if test="normalize-space($content) != ''">
    <xsl:text>_</xsl:text>
    <xsl:value-of select="normalize-space($content)"/>
    <xsl:text>_</xsl:text>
  </xsl:if>
  </xsl:template>

  <xsl:template match="b">
  <!-- Add a separating space if we follow an <italic> sibling directly, so
       "<italic>Denisia cryptica</italic><bold>sp. nov.</bold>" doesn't render
       as the joined "_Denisia cryptica_**sp. nov.**". Also strip edge
       whitespace inside the markers so "** text **" stays as "**text**". -->
  <xsl:if test="preceding-sibling::node()[1][self::italic]">
    <xsl:text> </xsl:text>
  </xsl:if>
  <xsl:variable name="content"><xsl:apply-templates /></xsl:variable>
  <xsl:if test="normalize-space($content) != ''">
    <xsl:text>**</xsl:text>
    <xsl:value-of select="normalize-space($content)"/>
    <xsl:text>**</xsl:text>
  </xsl:if>
  </xsl:template>


  <xsl:template match="ul">
    <xsl:apply-templates />
  </xsl:template>

  <xsl:template match="li">
  <xsl:text>&#xa;</xsl:text>
    <xsl:text>- </xsl:text>
     <xsl:apply-templates />
  </xsl:template>

<xsl:template match="h1">
    <xsl:text>&#xa;</xsl:text>
    <xsl:text># </xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
</xsl:template>

<xsl:template match="h2">
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>## </xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
</xsl:template> 


<xsl:template match="h3">
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>### </xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
</xsl:template> 


<xsl:template match="h4">
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>#### </xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
</xsl:template> 

  <!-- ================= HTML tables inside the Markdown stream =================
       The output method is text, so literal result elements (<table>…) would
       be stripped to their text content. Instead we emit the tags as literal
       text (in text output mode '<' and '>' are written verbatim, so no
       disable-output-escaping is needed) and process the whole table subtree
       in mode="html". That keeps inline emphasis as <strong>/<em> inside
       cells — Markdown '**'/'_' isn't parsed inside a raw-HTML table — without
       colliding with the Markdown templates used everywhere else. -->

  <!-- Entry: render a <table> as literal HTML on its own lines -->
  <xsl:template match="table">
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates select="." mode="html"/>
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

  <!-- Structural + cell elements: reproduce the tag with whitelisted attrs -->
  <xsl:template match="table | thead | tbody | tfoot | colgroup | col | tr | th | td" mode="html">
    <xsl:call-template name="emit-tag">
      <xsl:with-param name="close" select="false()"/>
    </xsl:call-template>
    <xsl:apply-templates mode="html"/>
    <xsl:call-template name="emit-tag">
      <xsl:with-param name="close" select="true()"/>
    </xsl:call-template>
  </xsl:template>

  <!-- Inline JATS -> HTML (only inside cells, via mode="html") -->
  <xsl:template match="bold" mode="html">
    <xsl:text>&lt;strong&gt;</xsl:text><xsl:apply-templates mode="html"/><xsl:text>&lt;/strong&gt;</xsl:text>
  </xsl:template>
  <xsl:template match="italic" mode="html">
    <xsl:text>&lt;em&gt;</xsl:text><xsl:apply-templates mode="html"/><xsl:text>&lt;/em&gt;</xsl:text>
  </xsl:template>
  <xsl:template match="sup" mode="html">
    <xsl:text>&lt;sup&gt;</xsl:text><xsl:apply-templates mode="html"/><xsl:text>&lt;/sup&gt;</xsl:text>
  </xsl:template>
  <xsl:template match="sub" mode="html">
    <xsl:text>&lt;sub&gt;</xsl:text><xsl:apply-templates mode="html"/><xsl:text>&lt;/sub&gt;</xsl:text>
  </xsl:template>
  <xsl:template match="break" mode="html">
    <xsl:text>&lt;br/&gt;</xsl:text>
  </xsl:template>
  <!-- footnote/cross-ref markers: keep the visible marker, drop the link -->
  <xsl:template match="xref" mode="html">
    <xsl:text>&lt;sup&gt;</xsl:text><xsl:apply-templates mode="html"/><xsl:text>&lt;/sup&gt;</xsl:text>
  </xsl:template>

  <!-- HTML-style inline elements inside cells (i/b/br/a). The templates above
       use JATS element names; these cover HTML input so italics/bold/links in
       cells (e.g. taxon names) survive instead of being flattened by the
       catch-all. -->
  <xsl:template match="i" mode="html">
    <xsl:text>&lt;em&gt;</xsl:text><xsl:apply-templates mode="html"/><xsl:text>&lt;/em&gt;</xsl:text>
  </xsl:template>
  <xsl:template match="b" mode="html">
    <xsl:text>&lt;strong&gt;</xsl:text><xsl:apply-templates mode="html"/><xsl:text>&lt;/strong&gt;</xsl:text>
  </xsl:template>
  <xsl:template match="br" mode="html">
    <xsl:text>&lt;br/&gt;</xsl:text>
  </xsl:template>
  <!-- Keep hyperlinks as HTML links (preserve href); hrefless anchors keep only text -->
  <xsl:template match="a" mode="html">
    <xsl:choose>
      <xsl:when test="@href">
        <xsl:text>&lt;a href="</xsl:text><xsl:value-of select="@href"/><xsl:text>"&gt;</xsl:text>
        <xsl:apply-templates mode="html"/>
        <xsl:text>&lt;/a&gt;</xsl:text>
      </xsl:when>
      <xsl:otherwise>
        <xsl:apply-templates mode="html"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- Text inside a cell: collapse the source's pretty-print whitespace.
       Without this, text nodes in mode="html" hit XSLT's built-in template,
       which copies them VERBATIM (newlines + indentation from datalab's
       pretty-printed HTML), breaking the table onto multiple lines. Mirror the
       edge-space handling of the default text() template so mixed cells like
       "word <b>x</b>" don't word-join. -->
  <xsl:template match="text()" mode="html">
    <xsl:variable name="n" select="normalize-space(.)"/>
    <xsl:if test="$n != ''">
      <xsl:if test="translate(substring(., 1, 1), ' &#9;&#10;&#13;', '') = ''">
        <xsl:text> </xsl:text>
      </xsl:if>
      <xsl:value-of select="$n"/>
      <xsl:if test="translate(substring(., string-length(.)), ' &#9;&#10;&#13;', '') = ''">
        <xsl:text> </xsl:text>
      </xsl:if>
    </xsl:if>
  </xsl:template>

  <!-- Any other element inside a cell: keep its text, drop the tag -->
  <xsl:template match="*" mode="html">
    <xsl:apply-templates mode="html"/>
  </xsl:template>

  <!-- Emit an opening or closing tag for the current element -->
  <xsl:template name="emit-tag">
    <xsl:param name="close" select="false()"/>
    <xsl:text>&lt;</xsl:text>
    <xsl:if test="$close"><xsl:text>/</xsl:text></xsl:if>
    <xsl:value-of select="local-name()"/>
    <xsl:if test="not($close)">
      <xsl:for-each select="@colspan | @rowspan | @align | @valign | @scope">
        <xsl:text> </xsl:text>
        <xsl:value-of select="local-name()"/>
        <xsl:text>="</xsl:text>
        <xsl:value-of select="."/>
        <xsl:text>"</xsl:text>
      </xsl:for-each>
    </xsl:if>
    <xsl:text>&gt;</xsl:text>
  </xsl:template>

  <xsl:template match="img">
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>![]</xsl:text>
    <xsl:text>(</xsl:text>
    <xsl:value-of select="@src"/>
    <xsl:text>)</xsl:text>
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>  

</xsl:stylesheet>
