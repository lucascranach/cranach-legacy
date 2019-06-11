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

$xmlFile = array();
$xmlFile["graphics"] = "./import-file/20190522/CDA-GR_DatenÅbersichtAlles_20190522.xml";
$xmlFile["paintings"] = "./import-file/20190522/CDA_DatenÅbersichtAlles_P1_20190522.xml";

define("HUGOCONTENT", "/Users/cnoss/git/cranach-graphics/content");
define("HUGODATA", "/Users/cnoss/git/cranach-graphics/data");

define("KIRBYCONTENT", "/Users/cnoss/git/cranach-graphics/content-kirby");
define("KIRBYDATA", "/Users/cnoss/git/cranach-graphics/data-kirby");

define("THUMBNAILROOT", "/Users/cnoss/git/cranach-data/thumbnails");
define("GRAPHICSROOT", "/Users/cnoss/git/cranach/importer/cranach-image-tools/images/dist");
define("MAPPINGVIRTUALIMAGES", file_get_contents("./import-file/mapping.json"));

require_once "helper.php";

class Collection
{
    public $items = array();

    public function __construct($type = "graphics", $limit = false)
    {
        $thumbCollection = new ImageCollection;
        $this->thumbs = $thumbCollection->images;
        $this->mappingVirtualImages = $this->getMappingVirtualImages();
        $this->type = $type;
        $this->limit = $limit;
    }

    private function getGraphicThumb($inventarnummer)
    {
        $id = "G_" . $inventarnummer;
        $images = $this->mappingVirtualImages->$id;

        if (sizeof($images) > 0) {
            return array_shift($images);
        } else {
            return false;
        }
    }

    private function getPaintingThumb($inventarnummer, $objectName)
    {
        $pattern = "=$inventarnummer\_$objectName.*?Overall\.jpg=";
        $thumbs = preg_grep($pattern, $this->thumbs);
        return "http://lucascranach.org/thumbnails/" . $inventarnummer . "_$objectName/01_Overall/" . array_pop($thumbs);
    }

    private function getThumb($item, $type = "graphics")
    {
        $inventarnummer = $item->Inventarnummer;
        $objectName = $item->ObjectName;
        if ($type === "graphics") {
            return $this->getGraphicThumb($inventarnummer);
        } else {
            return $this->getPaintingThumb($inventarnummer, $objectName);
        }
    }

    private function getMappingVirtualImages()
    {
        return json_decode(MAPPINGVIRTUALIMAGES);
    }

    public function addImages()
    {
        $itemsWithImage = array();
        foreach ($this->items as &$item) {
            $thumb = $this->getThumb($item);
            if ($thumb) {
                $item->BildURL = $thumb;
            } else {
                unset($item);
            }
        }
    }

    public function addItem($item)
    {
        array_push($this->items, $item);
    }

    public function doStore($fn, $data)
    {
        file_put_contents($fn, json_encode($data));
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
        $basePath = HUGODATA;
        $workingPath = "/" . $this->type;
        $workingPath = $this->checkPathSegements($basePath, $workingPath);
        $this->doStore($basePath . $workingPath . "/cdaGraphicsVirtualObjects.json", $this->getObjects("virtual"));
        print "Virtual Objects stored in $basePath$workingPath\n";
    }

    public function storeObjects()
    {
        $basePath = HUGODATA;
        $workingPath = "/" . $this->type;
        $workingPath = $this->checkPathSegements($basePath, $workingPath);
        $this->doStore($basePath . $workingPath . "/cdaObjects.json", $this->getObjects());
        print "Objects stored in $basePath$workingPath\n";
    }

    private function createDirectory($targetPath)
    {
        print "Create $targetPath\n";
        mkdir($targetPath, 0775);
    }

    private function checkPathSegements($basePath, $workingPath)
    {
        $pathSegments = explode("/", $workingPath);
        $targetPath = "/" . array_shift($pathSegments);

        foreach ($pathSegments as $segment) {
            $targetPath .= "$segment";
            if (!file_exists($basePath . $targetPath)) {
                $this->createDirectory($basePath . $targetPath);
            }
        }
        return $targetPath;
    }

    private function createHugoObject($item, $basePath = false)
    {

        if (!isset($basePath)) { die;}
        $fn = slugify($item->Title["de"]);
        $contentFolder = $basePath . "/$fn";

        if (!file_exists($contentFolder)) {mkdir($contentFolder, 0775);}

        $mdFn = $contentFolder . "/index.md";
        $mdContent = array("---");

        $item->Longtext["de"] = $item->Longtext["en"];

        foreach ($item as $key => $value) {

            $v = (gettype($value) == "array") ? $value["de"] : $value;

            
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

    private function createKirbyObject($item, $basePath = false)
    {
        if (!isset($basePath)) { die;}
        $fn = slugify($item->Title["de"]);
        $contentFolder = $basePath . "/$fn";
        if (!file_exists($contentFolder)) {mkdir($contentFolder, 0775);}

        $mdFn = $contentFolder . "/item-page.txt";
        $mdContent = array();
        $thumb = $this->getThumb($item);
        if ($thumb === false) {return false;}
        array_push($mdContent, "BildURL: $thumb");
        array_push($mdContent, "\n----\n");

        foreach ($item as $key => $value) {
            $v = (gettype($value) == "array") ? $value["de"] : $value;

            if (strlen($v) > 0) {

                switch (gettype($v)) {
                    case "integer":
                        array_push($mdContent, "$key: $v");
                        array_push($mdContent, "\n----\n");
                        break;
                    case "string":
                        $v = preg_replace("='=", "\"", $v);
                        array_push($mdContent, "$key: $v");
                        array_push($mdContent, "\n----\n");
                        break;
                }
            }
        }
        file_put_contents($mdFn, join("\n", $mdContent));
    }

    private function cleanFolder($path)
    {
        if (!isset($path)) {die;}
        $cmd = "find $path -type f";
        exec($cmd, $files);
        foreach($files as $file){ unlink($file); }

        $cmd = "find $path -type d";
        exec($cmd, $folder);
       foreach($folder as $item){ 
        if($item !== $path ) {
          rmdir($item); 
        }
      }

    }

    public function createData($channel = "HUGO")
    {
        $basePath = ($channel === "HUGO") ? HUGOCONTENT : KIRBYCONTENT;
        $workingPath = "/" . $this->type;
        $workingPath = $this->checkPathSegements($basePath, $workingPath);

        $this->cleanFolder($basePath . $workingPath);
        foreach ($this->items as $item) {
            if (isset($item->BildURL) && preg_match("=[a-z]=i", $item->BildURL) ) {
              print $item->BildURL . "\n";
                if ($channel === "HUGO") {
                    $this->createHugoObject($item, $basePath . $workingPath);
                } else {
                    $this->createKirbyObject($item, $basePath . $workingPath);
                }
            }
        }
        print "$channel Data created in $basePath$workingPath\n";
    }
}

class ImageCollection
{

    public $images = array();

    public function __construct()
    {

        $cmd = "find " . THUMBNAILROOT . " -name *.jpg";
        exec($cmd, $files);
        foreach ($files as $file) {
            $fn = preg_replace("=.*/=", "", $file);
            array_push($this->images, $fn);
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

    if (gettype($node->Field) === "object") {
        foreach ($node->Field as $field) {
            if ($field["Name"] == $fieldname) {
                if ($needle && $field->FormattedValue == $needle) {
                    return $returnValue ? $returnValue : $field->FormattedValue;
                } else {
                    return $field->FormattedValue;
                }
            }
        }
    } else {
        return false;
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

function iterateGroups($xml, $collection, $limit)
{
    foreach ($xml->Group as $group) {
        $graphic = new Graphic;

        $exit++;if ($exit < $limit) {parseGroup($group, $graphic, $collection);}
    }
}

$exit = 0;

if (!isset($argv[1]) || ($argv[1] !== "graphics" && $argv[1] !== "paintings")) {
    print "Was soll ausgespielt werden [graphics|paintings]?\nphp import.php graphics [20]\nphp import.php paintings [20]\n\n";
    exit;
}

$limit = (isset($argv[2]) && is_numeric($argv[2])) ? $argv[2] : false;

$type = preg_replace('/\s+/', ' ', trim($argv[1]));
$collection = new Collection($type, $limit);

if (file_exists($xmlFile[$collection->type])) {
    $xml = simplexml_load_file($xmlFile[$collection->type]);
    iterateGroups($xml, $collection, $limit);
    $collection->addImages();
    if ($collection->type === "graphics") {
        $collection->storeVirtualObjects();
    }
    $collection->storeObjects();
    $collection->createData("HUGO");
    $collection->createData("KIRBY");
} else {
    exit("Datei $xmlFile kann nicht geöffnet werden.");
}
