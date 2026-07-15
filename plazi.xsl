<?xml version='1.0' encoding='utf-8'?>
<xsl:stylesheet version='1.0' 
	xmlns:xsl='http://www.w3.org/1999/XSL/Transform' 
	xmlns:mods="http://www.loc.gov/mods/v3"
	exclude-result-prefixes="mods">
<xsl:output method='text' version='1.0' encoding='utf-8'  />


  <!-- Only process the treatment. This ignores the document front-matter and the
       MODS metadata block (title/authors/journal) for now — without it the
       default XSLT walk dumps all that text at the top of the output. -->
  <xsl:template match="/">
    <xsl:apply-templates select="//treatment"/>
  </xsl:template>

  <xsl:template match="treatment">
    <xsl:apply-templates />
  </xsl:template>

  <!-- Headings: Plazi marks a real heading with <heading level="N"> (e.g. the
       taxon name). Emit it as a Markdown ATX heading of the matching depth,
       flattening any inner emphasis to plain text so we don't get "## **...**".
       Section labels like "Diagnosis."/"Etymology." are NOT headings here — they
       are inline bold runs inside the paragraphs, so they are left as-is. -->
  <xsl:template match="heading">
    <xsl:variable name="level">
      <xsl:choose>
        <xsl:when test="@level"><xsl:value-of select="@level"/></xsl:when>
        <xsl:otherwise>2</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:text>&#xa;</xsl:text>
    <xsl:value-of select="substring('######', 1, $level)"/>
    <xsl:text> </xsl:text>
    <xsl:call-template name="tidy-punct">
      <xsl:with-param name="text" select="normalize-space(.)"/>
    </xsl:call-template>
    <xsl:text>&#xa;&#xa;</xsl:text>
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

  <!-- Replace every occurrence of one substring with another (XSLT 1.0). -->
  <xsl:template name="replace-all">
    <xsl:param name="text"/>
    <xsl:param name="find"/>
    <xsl:param name="repl"/>
    <xsl:choose>
      <xsl:when test="contains($text, $find)">
        <xsl:value-of select="substring-before($text, $find)"/>
        <xsl:value-of select="$repl"/>
        <xsl:call-template name="replace-all">
          <xsl:with-param name="text" select="substring-after($text, $find)"/>
          <xsl:with-param name="find" select="$find"/>
          <xsl:with-param name="repl" select="$repl"/>
        </xsl:call-template>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="$text"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- Collapse any run of spaces down to a single space (repeat until none left,
       so odd-length runs like 3 spaces don't leave a stray one behind). -->
  <xsl:template name="collapse-spaces">
    <xsl:param name="text"/>
    <xsl:choose>
      <xsl:when test="contains($text, '  ')">
        <xsl:variable name="once">
          <xsl:call-template name="replace-all">
            <xsl:with-param name="text" select="$text"/>
            <xsl:with-param name="find" select="'  '"/><xsl:with-param name="repl" select="' '"/>
          </xsl:call-template>
        </xsl:variable>
        <xsl:call-template name="collapse-spaces">
          <xsl:with-param name="text" select="$once"/>
        </xsl:call-template>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="$text"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- Tidy spacing artefacts left by element boundaries: collapse double spaces,
       remove a space before . , ; : ) ], and after ( [. These appear because
       punctuation/brackets often sit in their own text node next to an inline
       element (e.g. "holotype .", "( CUMZ 5100 )"). Only targeted replacements,
       so newlines (e.g. a heading inside a paragraph) are left untouched.
       Double-space is collapsed first so "word  ," becomes "word," cleanly. -->
  <xsl:template name="tidy-punct">
    <xsl:param name="text"/>
    <xsl:variable name="v1">
      <xsl:call-template name="collapse-spaces">
        <xsl:with-param name="text" select="$text"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="v2">
      <xsl:call-template name="replace-all">
        <xsl:with-param name="text" select="$v1"/>
        <xsl:with-param name="find" select="' .'"/><xsl:with-param name="repl" select="'.'"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="v3">
      <xsl:call-template name="replace-all">
        <xsl:with-param name="text" select="$v2"/>
        <xsl:with-param name="find" select="' ,'"/><xsl:with-param name="repl" select="','"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="v4">
      <xsl:call-template name="replace-all">
        <xsl:with-param name="text" select="$v3"/>
        <xsl:with-param name="find" select="' ;'"/><xsl:with-param name="repl" select="';'"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="v5">
      <xsl:call-template name="replace-all">
        <xsl:with-param name="text" select="$v4"/>
        <xsl:with-param name="find" select="' :'"/><xsl:with-param name="repl" select="':'"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="v6">
      <xsl:call-template name="replace-all">
        <xsl:with-param name="text" select="$v5"/>
        <xsl:with-param name="find" select="'( '"/><xsl:with-param name="repl" select="'('"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="v7">
      <xsl:call-template name="replace-all">
        <xsl:with-param name="text" select="$v6"/>
        <xsl:with-param name="find" select="' )'"/><xsl:with-param name="repl" select="')'"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="v8">
      <xsl:call-template name="replace-all">
        <xsl:with-param name="text" select="$v7"/>
        <xsl:with-param name="find" select="'[ '"/><xsl:with-param name="repl" select="'['"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:call-template name="replace-all">
      <xsl:with-param name="text" select="$v8"/>
      <xsl:with-param name="find" select="' ]'"/><xsl:with-param name="repl" select="']'"/>
    </xsl:call-template>
  </xsl:template>

  <!-- basic elements -->
  <xsl:template match="paragraph">
    <xsl:variable name="content"><xsl:apply-templates /></xsl:variable>
    <xsl:text>&#xa;</xsl:text>
    <xsl:call-template name="tidy-punct">
      <xsl:with-param name="text" select="$content"/>
    </xsl:call-template>
    <xsl:text>&#xa;&#xa;</xsl:text>
  </xsl:template>

<xsl:template match="caption">
    <xsl:variable name="content"><xsl:apply-templates /></xsl:variable>
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>&#xa;</xsl:text>
    <xsl:text>![]</xsl:text>
    <xsl:text>(</xsl:text>
    <xsl:value-of select="concat(@id, '.png')"/>
    <xsl:text>)</xsl:text>
    <xsl:text>&#xa;</xsl:text>
    <xsl:call-template name="tidy-punct">
      <xsl:with-param name="text" select="$content"/>
    </xsl:call-template>
    <xsl:text>&#xa;</xsl:text>
  </xsl:template>

<xsl:template match="emphasis">
  <!-- Collapse the content and trim edge whitespace so "** text **" (which
       Markdown won't render as bold) becomes "**text**", then tidy spaces that
       inline children leave before punctuation, e.g. "Description of holotype ."
       -> "Description of holotype.". Surrounding spacing comes from the
       neighbouring text nodes, not from inside the markers. -->
  <xsl:variable name="content"><xsl:apply-templates/></xsl:variable>
  <xsl:variable name="clean">
    <xsl:call-template name="tidy-punct">
      <xsl:with-param name="text" select="normalize-space($content)"/>
    </xsl:call-template>
  </xsl:variable>
  <xsl:if test="$clean != ''">
    <xsl:choose>
      <!-- Bold and italic -->
      <xsl:when test="@bold='true' and @italics='true'">
        <xsl:text>***</xsl:text><xsl:value-of select="$clean"/><xsl:text>***</xsl:text>
      </xsl:when>

      <!-- Bold -->
      <xsl:when test="@bold='true'">
        <xsl:text>**</xsl:text><xsl:value-of select="$clean"/><xsl:text>**</xsl:text>
      </xsl:when>

      <!-- Italic -->
      <xsl:when test="@italics='true'">
        <xsl:text>*</xsl:text><xsl:value-of select="$clean"/><xsl:text>*</xsl:text>
      </xsl:when>

      <!-- No formatting -->
      <xsl:otherwise>
        <xsl:value-of select="$clean"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:if>
</xsl:template>


</xsl:stylesheet>