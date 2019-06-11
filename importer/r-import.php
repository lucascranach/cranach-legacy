<?php

/* Fragen:

- Unterschied zwischen GR-Daten und GR-Rest
- Klassifizierung der Daten: Was sind Werknormdaten, wie sind die Daten verbunden.
- Bitte mal Bilder schicken
- Frontend Entwurf besprechen
- Mengengerüst
- Was ist primärer Schlüssel?
- Gibt es einen Loop durch die verschiedenen Typen (Gemälde, Literatur, etc.)
- Wofür ist die Metadaten Tabelle?
- Was macht der Thesaurus?

 */

$xmlFile = "./import-file/20190328/CDA-GR_DatenCn.xml";

define("HUGOCONTENT", "/Users/cnoss/git/cranach-graphics/content/graphics");
define("HUGODATA", "/Users/cnoss/git/cranach-graphics/data");

require_once "helper.php";

class Collection
{
    public $items = array();

    public function addItem($item)
    {
        array_push($this->items, $item);
    }

    public function doStore($fn, $data)
    {
        file_put_contents($fn, json_encode($data));
    }

    public function store()
    {
        $this->doStore("cda-graphics.json", $this);
    }

    private function getObjects($type = false)
    {
        $objects = array();

        if (isset($type)) {
            foreach ($this->items as $item) {if ($item->Type == $type) {$objects[$item->Oid] = $item;}}
            return $objects;
        } else {
            foreach ($this->items as $item) {$objects[$item->Oid] = $item;}
            return $objects;
        }

    }

    public function storeVirtualObjects()
    {
        $this->doStore(HUGODATA . "/cdaGraphicsVirtualObjects.json", $this->getObjects("virtual"));
    }

    public function storeObjects()
    {
        $this->doStore(HUGODATA . "/cdaGraphicsObjects.json", $this->getObjects());
    }

    private function createHugoObject( $item ){
      $fn = slugify($item->Title["de"]);
      $contentFolder = HUGOCONTENT . "/$fn";
      if (!file_exists($contentFolder)) {mkdir($contentFolder, 0775);}

      $mdFn = $contentFolder . "/index.md";
      $mdContent = array("---");
      array_push($mdContent, "img: \"http://lorempixel.com/400/700/abstract/\"");

      foreach ($item as $key => $value) {
          $v = (gettype($value) == "array") ? $value["de"] : $value;
          #if (strlen($v) > 0) {array_push($mdContent, "## $key\n$v\n");}
          if (strlen($v) > 0) {
    
              switch (gettype($v)) {
                  case "integer":
                      array_push($mdContent, "$key: $v");
                      break;
                  case "string":
                      $v = preg_replace("='=", "\"", $v);
                      array_push($mdContent, "$key: '$v'");
                      break;
              }

          }
      }

      array_push($mdContent, "---");
      file_put_contents($mdFn, join("\n", $mdContent));
    }

    public function createHugoData()
    {
        if (!file_exists(HUGOCONTENT)) {mkdir(HUGOCONTENT, 0775);}
        foreach ($this->items as $item) {

          if($item->Type === "virtual" ){
            $this->createHugoObject($item);
        }


        }

    }
}

class Graphic
{

    public function setType(string $newType)
    {
        $this->Type = $newType;
    }

    public function setOid(int $newOid)
    {
        $this->Oid = $newOid;
    }

    public function setObjectname(string $OBJECTNAME)
    {
        $this->ObjectName = $OBJECTNAME;
    }

    public function setInventarnummer(string $Inventarnummer)
    {
        $this->Inventarnummer = $Inventarnummer;
    }

    public function setDated(int $DATED)
    {
        $this->Dated = $DATED;
    }

    public function setDateBeginn(int $DateBeginn)
    {
        $this->DateBeginn = $DateBeginn;
    }

    public function setDateEnd(int $DateEnd)
    {
        $this->DateEnd = $DateEnd;
    }

    public function setTitle(string $langkey = "de", string $newTitle)
    {
        $this->Title[$langkey] = $newTitle;
        if ($langkey == "de") {$this->Link = slugify($newTitle);}
    }

    public function setRemarks(string $langkey = "de", string $newRemark)
    {
        $this->Remarks[$langkey] = $newRemark;
    }

    public function setZuschreibung(string $langkey = "de", string $Zuschreibung)
    {
        $this->Zuschreibung[$langkey] = $Zuschreibung;
    }

    public function setClassification(string $langkey = "de", string $newClassification)
    {
        $this->Classification[$langkey] = $newClassification;
    }

    public function setCondition(string $langkey = "de", string $newCondition)
    {
        $this->Condition[$langkey] = $newCondition;
    }

    public function setDimensions(string $langkey = "de", string $DIMENSIONS)
    {
        $this->Dimensions[$langkey] = $DIMENSIONS;
    }

    public function setLongtext(string $langkey = "de", string $LONGTEXT)
    {   
        $this->Longtext[$langkey] = $LONGTEXT;

    }

    /* Sprachbezogen? */
    public function setRelatedWorks(string $langkey = "de", string $RELATEDWORKS)
    {
        $this->RelatedWorks[$langkey] = $RELATEDWORKS;
    }

    public function setProvenienz(string $langkey = "de", string $Provenienz)
    {
        $this->Provenienz[$langkey] = $Provenienz;
    }

    public function setMaterialTechnik(string $langkey = "de", string $MaterialTechnik)
    {
        $this->MaterialTechnik[$langkey] = $MaterialTechnik;
    }

    public function setDatierungKuenstlersignaturUnterzeichner(string $langkey = "de", string $DatierungKuenstlersignaturUnterzeichner)
    {
        $this->DatierungKuenstlersignaturUnterzeichner[$langkey] = $DatierungKuenstlersignaturUnterzeichner;
    }

    public function setBeschriftung(string $langkey = "de", string $Beschriftung)
    {
        $this->Beschriftung[$langkey] = $Beschriftung;
    }

    public function setStempelZeichen(string $langkey = "de", string $StempelZeichen)
    {
        $this->StempelZeichen[$langkey] = $StempelZeichen;
    }

    public function setVerwandteArbeiten(string $langkey = "de", string $VerwandteArbeiten)
    {
        $this->VerwandteArbeiten[$langkey] = $VerwandteArbeiten;
    }

    public function setAusstellungsgeschichte(string $langkey = "de", string $Ausstellungsgeschichte)
    {
        $this->Ausstellungsgeschichte[$langkey] = $Ausstellungsgeschichte;
    }

    public function setLiteraturQuellen(string $LiteraturQuellen)
    {
        $this->DatierungKuenstlersignaturUnterzeichner = $LiteraturQuellen;
    }

}

function searchFieldForData($node, string $fieldname, $needle = false, $returnValue = false)
{

    if(gettype($node->Field) === "object"){
      foreach ($node->Field as $field) {
        if ($field["Name"] == $fieldname) {
            if ($needle && $field->FormattedValue == $needle) {
                return $returnValue ? $returnValue : $field->FormattedValue;
            } else {
                return $field->FormattedValue;
            }
        }
      }
    }else{
      return FALSE;
    }
}

function getLangContent(string $lang, string $content)
{
    if (!preg_match("=#=", $content)) {return $content;}
    $c = explode("#", $content);

    if ($lang == "de") {return $c[0];} else {
        return $c[1];
    }
}

function parseGroup($group, $graphic, $collection)
{
    $exit = 0;

    $graphic->setType((string) searchFieldForData($group->GroupHeader->Section[7], "ISVIRTUAL1", "1", "virtual"));
    $graphic->setOid((int) searchFieldForData($group->GroupHeader->Section[7], "OBJECTID1"));
    $graphic->setObjectname((string) searchFieldForData($group->GroupHeader->Section[5], "OBJECTNAME1"));
    $graphic->setInventarnummer((string) searchFieldForData($group->GroupHeader->Section[6], "Inventarnummer1"));

    # Title SectionNumber 3
    $graphic->setTitle("de", (string) searchFieldForData($group->GroupHeader->Section[3]->Subreport->Details[0]->Section[3], "TITLE1"));

    # Zuschreibung SectionNumber 1
    $graphic->setZuschreibung("de", (string) searchFieldForData($group->GroupHeader->Section[1]->Subreport->Details[0]->Section[2], "DISPLAYNAME1"));
    $graphic->setZuschreibung("en", (string) searchFieldForData($group->GroupHeader->Section[1]->Subreport->Details[1]->Section[2], "DISPLAYNAME1"));

    # DATED1 SectionNumber 9
    $graphic->setDated((int) searchFieldForData($group->GroupHeader->Section[9], "DATED1"));

    # DATEBEGIN SectionNumber 10
    $graphic->setDateBeginn((int) searchFieldForData($group->GroupHeader->Section[10], "DATEBEGIN1"));
    # DATEEND SectionNumber 11
    $graphic->setDateEnd((int) searchFieldForData($group->GroupHeader->Section[11], "DATEEND1"));

    # DESCRIPTION1 SectionNumber 14/15
    $graphic->setLongtext("de", (string) searchFieldForData($group->GroupHeader->Section[14], "DESCRIPTION1"));
    $graphic->setLongtext("en", (string) searchFieldForData($group->GroupHeader->Section[15], "LONGTEXT31"));
    
    # DATEREMARKS1 SectionNumber 12
    $graphic->setRemarks("de", (string) searchFieldForData($group->GroupHeader->Section[3]->Subreport->Details[0]->Section[3], "REMARKS1"));
    $graphic->setRelatedWorks("de", (string) searchFieldForData($group->GroupHeader->Section[26], "RELATEDWORKS1"));

    $classification = (string) searchFieldForData($group->GroupHeader->Section[4], "Klassifizierung1");
    $graphic->setClassification("de", getLangContent("de", $classification));
    $graphic->setClassification("en", getLangContent("en", $classification));

    $condition = (string) searchFieldForData($group->GroupHeader->Section[4], "Druckzustand1");
    $graphic->setCondition("de", getLangContent("de", $condition));
    $graphic->setCondition("en", getLangContent("en", $condition));

    $DIMENSIONS = (string) searchFieldForData($group->GroupHeader->Section[8], "Feld2");
    $graphic->setDimensions("de", getLangContent("de", $DIMENSIONS));
    $graphic->setDimensions("en", getLangContent("en", $DIMENSIONS));

    # PROVENANCE1 de/en SectionNumber 16/17
    $graphic->setProvenienz("de", (string) searchFieldForData($group->GroupHeader->Section[16], "PROVENANCE1"));
    $graphic->setProvenienz("en", (string) searchFieldForData($group->GroupHeader->Section[17], "PROVENANCE1"));

    # MEDIUM1 de/en SectionNumber 18/19
    $graphic->setMaterialTechnik("de", (string) searchFieldForData($group->GroupHeader->Section[18], "MEDIUM1"));
    $graphic->setMaterialTechnik("en", (string) searchFieldForData($group->GroupHeader->Section[19], "LONGTEXT41"));

    # PAPERSUPPORT2 de/en SectionNumber 20/21
    $graphic->setDatierungKuenstlersignaturUnterzeichner("de", (string) searchFieldForData($group->GroupHeader->Section[20], "PAPERSUPPORT2"));
    $graphic->setDatierungKuenstlersignaturUnterzeichner("en", (string) searchFieldForData($group->GroupHeader->Section[21], "SHORTTEXT61"));

    # INSCRIBED1 de/en SectionNumber 22/23
    $graphic->setBeschriftung("de", (string) searchFieldForData($group->GroupHeader->Section[22], "INSCRIBED1"));
    $graphic->setBeschriftung("en", (string) searchFieldForData($group->GroupHeader->Section[23], "LONGTEXT71"));

    # MARKINGS1 de/en SectionNumber 24/25
    $graphic->setStempelZeichen("de", (string) searchFieldForData($group->GroupHeader->Section[24], "INSCRIBED1"));
    $graphic->setStempelZeichen("en", (string) searchFieldForData($group->GroupHeader->Section[25], "LONGTEXT91"));

    # RELATEDWORKS1 de/en SectionNumber 26/27
    $graphic->setVerwandteArbeiten("de", (string) searchFieldForData($group->GroupHeader->Section[26], "RELATEDWORKS1"));
    $graphic->setVerwandteArbeiten("en", (string) searchFieldForData($group->GroupHeader->Section[27], "LONGTEXT61"));

    # EXHIBITIONS1 de/en SectionNumber 28/29
    $graphic->setAusstellungsgeschichte("de", (string) searchFieldForData($group->GroupHeader->Section[28], "EXHIBITIONS1"));
    $graphic->setAusstellungsgeschichte("en", (string) searchFieldForData($group->GroupHeader->Section[29], "LONGTEXT81"));

    # BIBLIOGRAPHY1 SectionNumber 30
    $graphic->setLiteraturQuellen((string) searchFieldForData($group->GroupHeader->Section[30], "BIBLIOGRAPHY1"));

    //$graphic->store();

    $collection->addItem($graphic);
}

function iterateGroups($xml, $collection)
{
    foreach ($xml->Group as $group) {
        $graphic = new Graphic;
        //$exit++;if ($exit > 1) {die;}
        //parseGroup($group, $graphic, $collection);
        $p = xml_parser_create();
        xml_parse_into_struct($p, $group, $vals, $index);
        xml_parser_free($p);
        echo "Index array\n";
        print_r($index);
        echo "\nVals array\n";
        print_r($vals);
        
        var_dump($group); exit;
    }
}

$exit = 0;
$collection = new Collection;


function xml2array($fname){
  $sxi = new SimpleXmlIterator($fname, null, true);
  return sxiToArray($sxi);
}

function sxiToArray($sxi){
  $a = array();
  for( $sxi->rewind(); $sxi->valid(); $sxi->next() ) {
    if(!array_key_exists($sxi->key(), $a)){
      $a[$sxi->key()] = array();
    }
    if($sxi->hasChildren()){
      $a[$sxi->key()][] = sxiToArray($sxi->current());
    }
    else{
      $a[$sxi->key()][] = strval($sxi->current());
    }
  }
  return $a;
}

$xml = simplexml_load_file($xmlFile);
file_put_contents("data-simple.json", json_encode($xml));

// Read cats.xml and print the results:
$xml = xml2array($xmlFile);
print_r($xmlFile);
file_put_contents("data.json", json_encode($xml));



if (file_exists($xmlFile)) {
  $xml = file_get_contents($xmlFile);
  $p = xml_parser_create();
  xml_parse_into_struct($p, $xml, $vals, $index);
  xml_parser_free($p);
  
  //print_r($index);
  
  //print_r($vals);

  file_put_contents("data-vals.json", json_encode($vals));
  



function traverse( DomNode $node, $level=0 ){
  handle_node( $node, $level );
 if ( $node->hasChildNodes() ) {
   $children = $node->childNodes;
   foreach( $children as $kid ) {
     if ( $kid->nodeType == XML_ELEMENT_NODE ) {
       traverse( $kid, $level+1 );
     }
   }
 }
}

function handle_node( DomNode $node, $level ) {
  for ( $x=0; $x<$level; $x++ ) {
    print " ";
  }
  if ( $node->nodeType == XML_ELEMENT_NODE ) {
    print_r($node);
  }
}

$doc = new DomDocument("1.0");
$doc->loadXML( file_get_contents($xmlFile));
$root = $doc->firstChild;
traverse( $root );

  exit;
    //$xml = simplexml_load_file($xmlFile);
    //iterateGroups($xml, $collection);
    //$collection->storeVirtualObjects();
    //$collection->storeObjects();
    //$collection->createHugoData();
} else {
    exit("Datei $xmlFile kann nicht geöffnet werden.");
}
