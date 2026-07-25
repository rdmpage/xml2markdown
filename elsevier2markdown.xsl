<?xml version='1.0' encoding='utf-8'?>
<xsl:stylesheet version='1.0'
  xmlns:xsl='http://www.w3.org/1999/XSL/Transform'
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:mml="http://www.w3.org/1998/Math/MathML"
  xmlns:ce="http://www.elsevier.com/xml/common/dtd"
  xmlns:ja="http://www.elsevier.com/xml/ja/dtd"
  xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"
  xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd"
  xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd"
  exclude-result-prefixes="ce ja cals sb xocs xlink mml">

  <xsl:output method='text' version='1.0' encoding='utf-8' />
  <xsl:strip-space elements="*"/>

  <!-- Only the article: title, authors, abstract, body, floats, references.
       Everything else (coredata, xocs metadata, ToC) is left out; the metadata
       is emitted as YAML front matter by elsevier2markdown.php instead. -->
  <xsl:template match="/">
    <!-- Title (H1, kept for readability alongside the front matter) -->
    <xsl:text># </xsl:text>
    <xsl:apply-templates select="(//ce:title)[1]/node()"/>
    <xsl:text>&#xa;&#xa;</xsl:text>

    <!-- Authors -->
    <xsl:for-each select="//ce:author">
      <xsl:if test="position() != 1">
        <xsl:choose>
          <xsl:when test="position() = last()"><xsl:text> and </xsl:text></xsl:when>
          <xsl:otherwise><xsl:text>, </xsl:text></xsl:otherwise>
        </xsl:choose>
      </xsl:if>
      <xsl:value-of select="ce:given-name"/>
      <xsl:text> </xsl:text>
      <xsl:value-of select="ce:surname"/>
    </xsl:for-each>
    <xsl:text>&#xa;&#xa;</xsl:text>

    <xsl:apply-templates select="(//ce:abstract[@class='author'])[1]"/>
    <xsl:apply-templates select="(//ce:sections)[1]"/>
    <xsl:apply-templates select="(//ce:floats)[1]"/>
    <xsl:apply-templates select="(//ce:bibliography)[1]"/>
  </xsl:template>

  <!-- Collapse whitespace but keep a single leading/trailing space when the
       source text node had one, so words don't join across element boundaries. -->
  <xsl:template match="text()">
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

  <!-- ===================== block elements ===================== -->

  <xsl:template match="ce:para">
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

  <xsl:template match="ce:simple-para">
    <xsl:text>&#xa;</xsl:text>
    <xsl:apply-templates />
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

  <!-- Abstract -->
  <xsl:template match="ce:abstract">
    <xsl:text>&#xa;## Abstract&#xa;</xsl:text>
    <xsl:apply-templates select="ce:abstract-sec"/>
  </xsl:template>
  <xsl:template match="ce:abstract-sec">
    <xsl:apply-templates />
  </xsl:template>

  <!-- Sections: heading level follows the nesting depth (article title is #). -->
  <xsl:template match="ce:section">
    <xsl:apply-templates />
  </xsl:template>

  <xsl:template match="ce:section-title">
    <xsl:variable name="depth" select="count(ancestor::ce:section)"/>
    <xsl:text>&#xa;</xsl:text>
    <xsl:value-of select="substring('######', 1, $depth + 1)"/>
    <xsl:text> </xsl:text>
    <!-- Fold the section number (a preceding ce:label sibling) into the heading,
         e.g. "## 2 Material and methods". -->
    <xsl:if test="preceding-sibling::ce:label">
      <xsl:value-of select="normalize-space(preceding-sibling::ce:label[1])"/>
      <xsl:text> </xsl:text>
    </xsl:if>
    <xsl:value-of select="normalize-space(.)"/>
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

  <!-- The section number is emitted as part of the heading above, so drop the
       standalone label (otherwise it would float as loose text before it). -->
  <xsl:template match="ce:section/ce:label"/>

  <!-- Lists -->
  <xsl:template match="ce:list">
    <xsl:apply-templates select="ce:list-item"/>
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>
  <xsl:template match="ce:list-item">
    <xsl:text>&#xa;- </xsl:text>
    <xsl:apply-templates />
  </xsl:template>

  <!-- ===================== inline elements ===================== -->

  <xsl:template match="ce:italic">
    <xsl:variable name="content"><xsl:apply-templates /></xsl:variable>
    <xsl:if test="normalize-space($content) != ''">
      <xsl:text>_</xsl:text><xsl:value-of select="normalize-space($content)"/><xsl:text>_</xsl:text>
    </xsl:if>
  </xsl:template>

  <xsl:template match="ce:bold">
    <xsl:variable name="content"><xsl:apply-templates /></xsl:variable>
    <xsl:if test="normalize-space($content) != ''">
      <xsl:text>**</xsl:text><xsl:value-of select="normalize-space($content)"/><xsl:text>**</xsl:text>
    </xsl:if>
  </xsl:template>

  <!-- sup/sub/cross-ref/label: keep the visible text, drop the markup/link -->
  <xsl:template match="ce:sup | ce:sub | ce:cross-ref | ce:cross-refs | ce:inter-ref | ce:intra-ref | ce:label">
    <xsl:apply-templates />
  </xsl:template>

  <!-- ===================== floats: figures + tables ===================== -->

  <xsl:template match="ce:floats">
    <xsl:apply-templates select="ce:figure | ce:table"/>
  </xsl:template>

  <!-- Figure: local image (downloaded by elsevier-images.php) + label + caption.
       ce:link/@locator matches an attachment's file-basename; use that
       attachment's downsampled filename so the link resolves to the saved file. -->
  <xsl:template match="ce:figure">
    <xsl:text>&#xa;&#xa;</xsl:text>
    <xsl:variable name="img"
      select="//xocs:attachment[xocs:file-basename = current()/ce:link/@locator
                                and xocs:attachment-type = 'IMAGE-DOWNSAMPLED']/xocs:filename"/>
    <xsl:if test="$img">
      <xsl:text>![](</xsl:text><xsl:value-of select="$img"/><xsl:text>)&#xa;&#xa;</xsl:text>
    </xsl:if>
    <xsl:text>**</xsl:text>
    <xsl:value-of select="normalize-space(ce:label)"/>
    <xsl:text>** </xsl:text>
    <xsl:value-of select="normalize-space(ce:caption)"/>
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

  <!-- Table: label + caption, then the CALS grid as a single-line HTML table
       (Markdown passes raw HTML through; GFM won't parse '**'/'_' inside it, so
       cell content is emitted as plain text). Column/row spans are not modelled. -->
  <xsl:template match="ce:table">
    <xsl:text>&#xa;&#xa;**</xsl:text>
    <xsl:value-of select="normalize-space(ce:label)"/>
    <xsl:text>** </xsl:text>
    <xsl:value-of select="normalize-space(ce:caption)"/>
    <xsl:text>&#xa;&#xa;</xsl:text>
    <xsl:text>&lt;table&gt;</xsl:text>
    <xsl:apply-templates select=".//*[local-name()='row']" mode="table"/>
    <xsl:text>&lt;/table&gt;</xsl:text>
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

  <xsl:template match="*[local-name()='row']" mode="table">
    <xsl:text>&lt;tr&gt;</xsl:text>
    <xsl:apply-templates select="*[local-name()='entry']" mode="table"/>
    <xsl:text>&lt;/tr&gt;</xsl:text>
  </xsl:template>

  <xsl:template match="*[local-name()='entry']" mode="table">
    <xsl:variable name="tag">
      <xsl:choose>
        <xsl:when test="ancestor::*[local-name()='thead'] or @role='rowhead'">th</xsl:when>
        <xsl:otherwise>td</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:text>&lt;</xsl:text><xsl:value-of select="$tag"/><xsl:text>&gt;</xsl:text>
    <xsl:value-of select="normalize-space(.)"/>
    <xsl:text>&lt;/</xsl:text><xsl:value-of select="$tag"/><xsl:text>&gt;</xsl:text>
  </xsl:template>

  <!-- ===================== references ===================== -->

  <xsl:template match="ce:bibliography">
    <xsl:text>&#xa;## References&#xa;</xsl:text>
    <xsl:apply-templates select=".//ce:bib-reference"/>
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>

  <!-- Prefer the ready-made ce:source-text citation; fall back to element text. -->
  <xsl:template match="ce:bib-reference">
    <xsl:text>&#xa;- </xsl:text>
    <xsl:choose>
      <xsl:when test=".//ce:source-text">
        <xsl:value-of select="normalize-space((.//ce:source-text)[1])"/>
      </xsl:when>
      <xsl:when test=".//ce:textref">
        <!-- Elsevier's unstructured fallback (ce:other-ref) -->
        <xsl:value-of select="normalize-space((.//ce:textref)[1])"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="normalize-space(.)"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

</xsl:stylesheet>
