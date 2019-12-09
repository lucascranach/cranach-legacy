<?php

namespace CranachImport\Importers\Inflators;

require_once 'IPaintingInflator.php';
require_once 'entities/Painting.php';

require_once 'entities/main/Person.php';
require_once 'entities/main/PersonName.php';
require_once 'entities/main/PersonNameDetail.php';
require_once 'entities/main/Title.php';
require_once 'entities/main/Dating.php';
require_once 'entities/main/HistoricEventInformation.php';
require_once 'entities/main/ObjectReference.php';
require_once 'entities/main/AdditionalTextInformation.php';
require_once 'entities/main/Publication.php';
require_once 'entities/main/MetaReference.php';
require_once 'entities/main/CatalogWorkReference.php';
require_once 'entities/main/StructuredDimension.php';

require_once 'entities/painting/Classification.php';


use CranachImport\Entities\Painting;

use CranachImport\Entities\Main\Person;
use CranachImport\Entities\Main\PersonName;
use CranachImport\Entities\Main\PersonNameDetail;
use CranachImport\Entities\Main\Title;
use CranachImport\Entities\Main\Dating;
use CranachImport\Entities\Main\HistoricEventInformation;
use CranachImport\Entities\Main\ObjectReference;
use CranachImport\Entities\Main\AdditionalTextInformation;
use CranachImport\Entities\Main\Publication;
use CranachImport\Entities\Main\MetaReference;
use CranachImport\Entities\Main\CatalogWorkReference;
use CranachImport\Entities\Main\StructuredDimension;

use CranachImport\Entities\Painting\Classification;


/**
 * Paintingss inflator used to inflate german and english painting instances
 * 	by traversing the xml element node and extracting the data in a structured way
 */
class PaintingsXMLInflator implements IPaintingInflator {

	private static $nsPrefix = 'ns';
	private static $ns = 'urn:crystal-reports:schemas:report-detail';
	private static $langSplitChar = '#';

	private static $additionalTextLanguageTypes = [
		'de' => 'Beschreibung/ Interpretation/ Kommentare',
		'en' => 'Description/ Interpretation/ Comments',
		'author' => 'Autor', /* TODO: To be checked; has german values? */
		'letter' => 'Briefumschrift', /* TODO: To be checked; has english values? */
		'not_assigned' => '(not assigned)',
	];

	private static $locationLanguageTypes = [
		'de' => 'Standort Cranach Objekt',
		'en' => 'Location Cranach Object',
		'not_assigned' => '(not assigned)',
	];

	private static $titlesLanguageTypes = [
		'de' => 'GERMAN',
		'en' => 'ENGLISH',
		'not_assigned' => '(not assigned)',
	];

	private static $inventoryNumberReplaceRegExpArr = [
		'/^CDA\./',
	];

	private function __construct() {}


	public static function inflate(\SimpleXMLElement &$node,
	                               Painting &$paintingDe,
	                               Painting &$paintingEn) {
		$subNode = $node->GroupHeader;

		self::registerXPathNamespace($subNode);
	
		self::inflateInvolvedPersons($subNode, $paintingDe, $paintingEn);
		self::inflatePersonNames($subNode, $paintingDe, $paintingEn);
		self::inflateTitles($subNode, $paintingDe, $paintingEn);
		self::inflateClassification($subNode, $paintingDe, $paintingEn);
		self::inflateObjectName($subNode, $paintingDe, $paintingEn);
		self::inflateInventoryNumber($subNode, $paintingDe, $paintingEn);
		self::inflateObjectMeta($subNode, $paintingDe, $paintingEn);
		self::inflateDimensions($subNode, $paintingDe, $paintingEn);
		self::inflateDating($subNode, $paintingDe, $paintingEn);
		self::inflateDescription($subNode, $paintingDe, $paintingEn);
		self::inflateProvenance($subNode, $paintingDe, $paintingEn);
		self::inflateMedium($subNode, $paintingDe, $paintingEn);
		self::inflateSignature($subNode, $paintingDe, $paintingEn);
		self::inflateInscription($subNode, $paintingDe, $paintingEn);
		self::inflateMarkings($subNode, $paintingDe, $paintingEn);
		self::inflateRelatedWorks($subNode, $paintingDe, $paintingEn);
		self::inflateExhibitionHistory($subNode, $paintingDe, $paintingEn);
		self::inflateBibliography($subNode, $paintingDe, $paintingEn);
		self::inflateReferences($subNode, $paintingDe, $paintingEn);
		self::inflateSecondaryReferences($subNode, $paintingDe, $paintingEn);
		self::inflateAdditionalTextInformations($subNode, $paintingDe, $paintingEn);
		self::inflatePublications($subNode, $paintingDe, $paintingEn);
		self::inflateKeywords($subNode, $paintingDe, $paintingEn);
		self::inflateLocations($subNode, $paintingDe, $paintingEn);
		self::inflateRepository($subNode, $paintingDe, $paintingEn);
		self::inflateOwner($subNode, $paintingDe, $paintingEn);
		self::inflateSortingNumber($subNode, $paintingDe, $paintingEn);
		self::inflateCatalogWorkReference($subNode, $paintingDe, $paintingEn);
		self::inflateStructuredDimension($subNode, $paintingDe, $paintingEn);
		self::inflateIsBestOf($subNode, $paintingDe, $paintingEn);
	}


	/* Involved persons */
	private static function inflateInvolvedPersons(\SimpleXMLElement &$node,
	                                               Painting &$paintingDe,
	                                               Painting &$paintingEn) {
		$details = $node->Section[1]->Subreport->Details;

		for ($i = 0; $i < count($details); $i += 2) {
			$personsArr = [
				new Person, // de
				new Person, // en
			];

			$paintingDe->addPerson($personsArr[0]);
			$paintingEn->addPerson($personsArr[1]);

			for ($j = 0; $j < count($personsArr); $j += 1) {
				$currDetails = $details[$i + $j];

				if (is_null($currDetails)) {
					continue;
				}

				/* role */
				$roleElement = self::findElementByXPath(
					$currDetails,
					'Field[@FieldName="{ROLES.Role}"]/FormattedValue',
				);
				if ($roleElement) {
					$roleStr = trim($roleElement);
					$personsArr[$j]->setRole($roleStr);
				}

				/* name */
				$nameElement = self::findElementByXPath(
					$currDetails,
					'Field[@FieldName="{CONALTNAMES.DisplayName}"]/FormattedValue',
				);
				if ($nameElement) {
					$nameStr = trim($nameElement);
					$personsArr[$j]->setName($nameStr);
				}

				/* prefix */
				$prefixElement = self::findElementByXPath(
					$currDetails,
					'Section[@SectionNumber="3"]//FormattedValue',
				);
				if ($prefixElement) {
					$prefixStr = trim($prefixElement);
					$personsArr[$j]->setPrefix($prefixStr);
				}

				/* suffix */
				$suffixElement = self::findElementByXPath(
					$currDetails,
					'Section[@SectionNumber="4"]//FormattedValue',
				);
				if ($suffixElement) {
					$suffixStr = trim($suffixElement);
					$personsArr[$j]->setSuffix($suffixStr);
				}

				/* role of unknown person */
				$unknownPersonRoleElement = self::findElementByXPath(
					$currDetails,
					'Section[@SectionNumber="6"]//FormattedValue',
				);
				if ($unknownPersonRoleElement) {
					/* with a role set for an unknown person,
						we can mark the person as 'unknown' */
					$personsArr[$j]->setIsUnknown(true);

					$unknownPersonRoleStr = trim($unknownPersonRoleElement);
					$personsArr[$j]->setRole($unknownPersonRoleStr);
				}

				/* prefix of unknown person */
				$unknownPersonPrefixElement = self::findElementByXPath(
					$currDetails,
					'Section[@SectionNumber="7"]//FormattedValue',
				);
				if ($unknownPersonPrefixElement) {
					$unknownPersonPrefixStr = trim($unknownPersonPrefixElement);
					$personsArr[$j]->setPrefix($unknownPersonPrefixStr);
				}

				/* suffix of unknown person */
				$unknownPersonSuffixElement = self::findElementByXPath(
					$currDetails,
					'Section[@SectionNumber="8"]//FormattedValue',
				);
				if ($unknownPersonSuffixElement) {
					$unknownPersonSuffixStr = trim($unknownPersonSuffixElement);
					$personsArr[$j]->setSuffix($unknownPersonSuffixStr);
				}

				/* name type */
				$nameTypeElement = self::findElementByXPath(
					$currDetails,
					'Field[@FieldName="{@Nametype}"]/FormattedValue',
				);
				if ($nameTypeElement) {
					$nameTypeStr = trim($nameTypeElement);
					$personsArr[$j]->setNameType($nameTypeStr);
				}

				/* alternative name */
				$alternativeNameElement = self::findElementByXPath(
					$currDetails,
					'Field[@FieldName="{@AndererName}"]/FormattedValue',
				);
				if ($alternativeNameElement) {
					$alternativeNameStr = trim($alternativeNameElement);
					$personsArr[$j]->setAlternativeName($alternativeNameStr);
				}

				/* remarks */
				$remarksElement = self::findElementByXPath(
					$currDetails,
					'Section[@SectionNumber="11"]//FormattedValue',
				);
				if ($remarksElement) {
					$remarksNameStr = trim($remarksElement);
					$personsArr[$j]->setRemarks($remarksNameStr);
				}

				/* date */
				$dateElement = self::findElementByXPath(
					$currDetails,
					'Section[@SectionNumber="12"]//FormattedValue',
				);
				if ($dateElement) {
					$dateStr = trim($dateElement);
					$personsArr[$j]->setDate($dateStr);
				}
			}
		}
	}


	/* Person names */
	private static function inflatePersonNames(\SimpleXMLElement &$node,
	                                           Painting &$paintingDe,
	                                           Painting &$paintingEn) {
		$groups = $node->Section[2]->Subreport->Group;

		foreach ($groups as $group) {
			$personName = new PersonName;

			$paintingDe->addPersonName($personName);
			$paintingEn->addPersonName($personName);

			/* constituent id */
			$constituentIdElement = self::findElementByXPath(
				$group,
				'Field[@FieldName="GroupName ({CONALTNAMES.ConstituentID})"]/FormattedValue',
			);
			if ($constituentIdElement) {
				$constituentIdStr = trim($constituentIdElement);
				$personName->setConstituentId($constituentIdStr);
			}

			$nameDetailGroups = self::findElementsByXPath(
				$group,
				'Group[@Level="2"]',
			);

			if (!$nameDetailGroups) {
				continue;
			}

			foreach ($nameDetailGroups as $nameDetailGroup) {
				$personDetailName = new PersonNameDetail;
				$personName->addDetail($personDetailName);

				/* name */
				$detailNameElement = self::findElementByXPath(
					$nameDetailGroup,
					'Field[@FieldName="GroupName ({CONALTNAMES.DisplayName})"]/FormattedValue',
				);
				if ($detailNameElement) {
					$detailNameStr = trim($detailNameElement);
					$personDetailName->setName($detailNameStr);
				}

				/* type */
				$detailNameTypeElement = self::findElementByXPath(
					$nameDetailGroup,
					'Field[@FieldName="GroupName ({CONALTNAMES.NameType})"]/FormattedValue',
				);
				if ($detailNameTypeElement) {
					$detailNameTypeStr = trim($detailNameTypeElement);
					$personDetailName->setNameType($detailNameTypeStr);
				}
			}
		}
	}


	/* Titles */
	private static function inflateTitles(\SimpleXMLElement &$node,
	                                      Painting &$paintingDe,
	                                      Painting &$paintingEn) {
		$titleDetailElements = $node->Section[3]->Subreport->Details;

		for ($i = 0; $i < count($titleDetailElements); $i += 1) {
			$titleDetailElement = $titleDetailElements[$i];

			if (is_null($titleDetailElement)) {
				continue;
			}

			$title = new Title;

			/* title language */
			$langElement = self::findElementByXPath(
				$titleDetailElement,
				'Field[@FieldName="{LANGUAGES.Language}"]/FormattedValue',
			);
			if ($langElement) {
				$langStr = trim($langElement);

				if (self::$titlesLanguageTypes['de'] === $langStr) {
					$paintingDe->addTitle($title);
				} else if (self::$titlesLanguageTypes['en'] === $langStr) {
					$paintingEn->addTitle($title);
				} else if(self::$titlesLanguageTypes['not_assigned'] === $langStr) {
					echo 'Unassigned title lang for object ' . $paintingDe->getInventoryNumber() . "\n";
				} else {
					echo 'Unknown title lang: ' . $langStr . ' for object ' . $paintingDe->getInventoryNumber() . "\n";
					/* Bind title to both languages to prevent loss */
					$paintingDe->addTitle($title);
					$paintingEn->addTitle($title);
				}
			} else {
				/* Bind title to both languages to prevent loss */
				$paintingDe->addTitle($title);
				$paintingEn->addTitle($title);
			}

			/* title type */
			$typeElement = self::findElementByXPath(
				$titleDetailElement,
				'Field[@FieldName="{TITLETYPES.TitleType}"]/FormattedValue',
			);
			if ($typeElement) {
				$typeStr = trim($typeElement);
				$title->setType($typeStr);
			}

			/* title */
			$titleElement = self::findElementByXPath(
				$titleDetailElement,
				'Field[@FieldName="{OBJTITLES.Title}"]/FormattedValue',
			);
			if ($titleElement) {
				$titleStr = trim($titleElement);
				$title->setTitle($titleStr);
			}

			/* remark */
			$remarksElement = self::findElementByXPath(
				$titleDetailElement,
				'Field[@FieldName="{OBJTITLES.Remarks}"]/FormattedValue',
			);
			if ($remarksElement) {
				$remarksStr = trim($remarksElement);
				$title->setRemarks($remarksStr);
			}
		}
	}


	/* Classification */
	private static function inflateClassification(\SimpleXMLElement &$node,
	                                              Painting &$paintingDe,
	                                              Painting &$paintingEn) {
		$classificationSectionElement = $node->Section[4];

		$classificationDe = new Classification;
		$classificationEn = new Classification;

		$paintingDe->setClassification($classificationDe);
		$paintingEn->setClassification($classificationEn);

		/* classification */
		$classificationElement = self::findElementByXPath(
			$classificationSectionElement,
			'Field[@FieldName="{@Klassifizierung}"]/FormattedValue',
		);
		if ($classificationElement) {
			$classificationStr = trim($classificationElement);

			/* Using single german value for both language objects */
			$classificationDe->setClassification($classificationStr);
			$classificationEn->setClassification($classificationStr);
		}
	}


	/* Object name */
	private static function inflateObjectName(\SimpleXMLElement &$node,
	                                          Painting &$paintingDe,
	                                          Painting &$paintingEn) {
		$objectNameSectionElement = $node->Section[5];

		$objectNameElement = self::findElementByXPath(
			$objectNameSectionElement,
			'Field[@FieldName="{OBJECTS.ObjectName}"]/FormattedValue',
		);
		if ($objectNameElement) {
			$objectNameStr = trim($objectNameElement);

			/* Using single german value for both language objects */
			$paintingDe->setObjectName($objectNameStr);
			$paintingEn->setObjectName($objectNameStr);
		}
	}


	/* Inventory number */
	private static function inflateInventoryNumber(\SimpleXMLElement &$node,
	                                               Painting &$paintingDe,
	                                               Painting &$paintingEn) {
		$inventoryNumberSectionElement = $node->Section[6];

		$inventoryNumberElement = self::findElementByXPath(
			$inventoryNumberSectionElement,
			'Field[@FieldName="{@Inventarnummer}"]/FormattedValue',
		);
		if ($inventoryNumberElement) {
			$inventoryNumberStr = trim($inventoryNumberElement);
			$cleanInventoryNumberStr = preg_replace(
				self::$inventoryNumberReplaceRegExpArr,
				'',
				$inventoryNumberStr,
			);

			/* Using single german value for both language objects */
			$paintingDe->setInventoryNumber($cleanInventoryNumberStr);
			$paintingEn->setInventoryNumber($cleanInventoryNumberStr);
		}
	}


	/* Object id & virtual (meta) */
	private static function inflateObjectMeta(\SimpleXMLElement &$node,
	                                          Painting &$paintingDe,
	                                          Painting &$paintingEn) {
		$metaSectionElement = $node->Section[7];

		/* object id */
		$objectIdElement = self::findElementByXPath(
			$metaSectionElement,
			'Field[@FieldName="{OBJECTS.ObjectID}"]/Value',
		);
		if ($objectIdElement) {
			$objectIdStr = intval(trim($objectIdElement));

			/* Using single german value for both language objects */
			$paintingDe->setObjectId($objectIdStr);
			$paintingEn->setObjectId($objectIdStr);
		}
	}


	/* Dimensions */
	private static function inflateDimensions(\SimpleXMLElement &$node,
	                                          Painting &$paintingDe,
	                                          Painting &$paintingEn) {
		$metaSectionElement = $node->Section[8];

		/* object id */
		$dimensionsElement = self::findElementByXPath(
			$metaSectionElement,
			'Field[@FieldName="{OBJECTS.Dimensions}"]/FormattedValue',
		);
		if ($dimensionsElement) {
			$dimensionsStr = trim($dimensionsElement);

			$splitDimensionsStr = self::splitLanguageString($dimensionsStr);

			if (isset($splitDimensionsStr[0])) {
				$paintingDe->setDimensions($splitDimensionsStr[0]);
			}

			if (isset($splitDimensionsStr[1])) {
				$paintingEn->setDimensions($splitDimensionsStr[1]);
			}
		}
	}


	/* Dating */
	private static function inflateDating(\SimpleXMLElement &$node,
	                                      Painting &$paintingDe,
	                                      Painting &$paintingEn) {
		$datingDe = new Dating;
		$datingEn = new Dating;

		/* Using single german value for both language objects */
		$paintingDe->setDating($datingDe);
		$paintingEn->setDating($datingEn);

		/* Dated (string) */
		$datedSectionElement = $node->Section[9];

		$datedElement = self::findElementByXPath(
			$datedSectionElement,
			'Field[@FieldName="{OBJECTS.Dated}"]/FormattedValue',
		);
		if ($datedElement) {
			$datedDateStr = trim($datedElement);

			$splitStateStr = self::splitLanguageString($datedDateStr);

			if (isset($splitStateStr[0])) {
				$datingDe->setDated($splitStateStr[0]);
			}

			if (isset($splitStateStr[1])) {
				$datingEn->setDated($splitStateStr[1]);
			}
		}

		/* Date begin */
		$dateBeginSectionElement = $node->Section[10];

		$dateBeginElement = self::findElementByXPath(
			$dateBeginSectionElement,
			'Field[@FieldName="{OBJECTS.DateBegin}"]/FormattedValue',
		);
		if ($dateBeginElement) {
			$dateBeginStr = intval(trim($dateBeginElement));

			$datingDe->setBegin($dateBeginStr);
			$datingEn->setBegin($dateBeginStr);
		}

		/* Date end */
		$dateEndSectionElement = $node->Section[11];

		$dateEndElement = self::findElementByXPath(
			$dateEndSectionElement,
			'Field[@FieldName="{OBJECTS.DateEnd}"]/FormattedValue',
		);
		if ($dateEndElement) {
			$dateEndStr = intval(trim($dateEndElement));

			$datingDe->setEnd($dateEndStr);
			$datingEn->setEnd($dateEndStr);
		}

		/* Remarks */
		$remarksSectionElement = $node->Section[12];

		$remarksElement = self::findElementByXPath(
			$remarksSectionElement,
			'Field[@FieldName="{OBJECTS.DateRemarks}"]/FormattedValue',
		);
		if ($remarksElement) {
			$remarksStr = trim($remarksElement);

			$splitRemarksStr = self::splitLanguageString($remarksStr);

			if (isset($splitRemarksStr[0])) {
				$datingDe->setRemarks($splitRemarksStr[0]);
			}

			if (isset($splitRemarksStr[1])) {
				$datingEn->setRemarks($splitRemarksStr[1]);
			}
		}

		/* HistoricEventInformation */
		$historicEventDetailElements = $node->Section[13]->Subreport->Details;

		for ($i = 0; $i < count($historicEventDetailElements); $i += 2) {
			$historicEventArr = [];

			// de
			$detailDeElement = $historicEventDetailElements[$i];
			if (!is_null($detailDeElement) && $detailDeElement->count() > 0) {
				$historicEventInformation = new HistoricEventInformation;
				$historicEventArr[] = $historicEventInformation;
				$datingDe->addHistoricEventInformation($historicEventInformation);
			}

			// en
			$detailEnElement = $historicEventDetailElements[$i + 1];
			if (!is_null($detailEnElement) && $detailEnElement->count() > 0) {
				$historicEventInformation = new HistoricEventInformation;
				$historicEventArr[] = $historicEventInformation;
				$datingEn->addHistoricEventInformation($historicEventInformation);
			}

			for ($j = 0; $j < count($historicEventArr); $j += 1) {
				$historicEventDetailElement = $historicEventDetailElements[$i + $j];

				if (is_null($historicEventDetailElement) || !isset($historicEventArr[$j])) {
					continue;
				}

				/* event type */
				$eventTypeElement = self::findElementByXPath(
					$historicEventDetailElement,
					'Field[@FieldName="{OBJDATES.EventType}"]/FormattedValue',
				);
				if ($eventTypeElement) {
					$eventTypeStr = trim($eventTypeElement);
					$historicEventArr[$j]->setEventType($eventTypeStr);
				}

				/* date text */
				$dateTextElement = self::findElementByXPath(
					$historicEventDetailElement,
					'Field[@FieldName="{OBJDATES.DateText}"]/FormattedValue',
				);
				if ($dateTextElement) {
					$dateTextStr = trim($dateTextElement);
					$historicEventArr[$j]->setText($dateTextStr);
				}

				/* begin date */
				$dateBeginElement = self::findElementByXPath(
					$historicEventDetailElement,
					'Field[@FieldName="{@Anfangsdatum}"]/FormattedValue',
				);
				if ($dateBeginElement) {
					$dateBeginNumber = intval(trim($dateBeginElement));
					$historicEventArr[$j]->setBegin($dateBeginNumber);
				}

				/* end date */
				$dateEndElement = self::findElementByXPath(
					$historicEventDetailElement,
					'Field[@FieldName="{@Enddatum }"]/FormattedValue',
				);
				if ($dateEndElement) {
					$dateEndNumber = intval(trim($dateEndElement));
					$historicEventArr[$j]->setEnd($dateEndNumber);
				}

				/* remarks */
				$dateRemarksElement = self::findElementByXPath(
					$historicEventDetailElement,
					'Field[@FieldName="{OBJDATES.Remarks}"]/FormattedValue',
				);
				if ($dateRemarksElement) {
					$dateRemarksNumber = trim($dateRemarksElement);
					$historicEventArr[$j]->setRemarks($dateRemarksNumber);
				}
			}
		}
	}


	/* Description */
	private static function inflateDescription(\SimpleXMLElement &$node,
	                                           Painting &$paintingDe,
	                                           Painting &$paintingEn) {
		/* de */
		$descriptionDeSectionElement = $node->Section[14];
		$descriptionElement = self::findElementByXPath(
			$descriptionDeSectionElement,
			'Field[@FieldName="{OBJECTS.Description}"]/FormattedValue',
		);
		if ($descriptionElement) {
			$descriptionStr = trim($descriptionElement);
			$paintingDe->setDescription($descriptionStr);
		}

		/* en */
		$descriptionEnSectionElement = $node->Section[15];
		$descriptionElement = self::findElementByXPath(
			$descriptionEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.LongText3}"]/FormattedValue',
		);
		if ($descriptionElement) {
			$descriptionStr = trim($descriptionElement);
			$paintingEn->setDescription($descriptionStr);
		}
	}


	/* Provenance */
	private static function inflateProvenance(\SimpleXMLElement &$node,
	                                          Painting &$paintingDe,
	                                          Painting &$paintingEn) {
		/* de */
		$provenanceDeSectionElement = $node->Section[16];
		$provenanceElement = self::findElementByXPath(
			$provenanceDeSectionElement,
			'Field[@FieldName="{OBJECTS.Provenance}"]/FormattedValue',
		);
		if ($provenanceElement) {
			$provenanceStr = trim($provenanceElement);
			$paintingDe->setProvenance($provenanceStr);
		}

		/* en */
		$provenanceEnSectionElement = $node->Section[17];
		$provenanceElement = self::findElementByXPath(
			$provenanceEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.LongText5}"]/FormattedValue',
		);
		if ($provenanceElement) {
			$provenanceStr = trim($provenanceElement);
			$paintingEn->setProvenance($provenanceStr);
		}
	}


	/* Medium */
	private static function inflateMedium(\SimpleXMLElement &$node,
	                                        Painting &$paintingDe,
	                                        Painting &$paintingEn) {
		/* de */
		$mediumDeSectionElement = $node->Section[18];
		$mediumElement = self::findElementByXPath(
			$mediumDeSectionElement,
			'Field[@FieldName="{OBJECTS.Medium}"]/FormattedValue',
		);
		if ($mediumElement) {
			$mediumStr = trim($mediumElement);
			$paintingDe->setMedium($mediumStr);
		}

		/* en */
		$mediumEnSectionElement = $node->Section[19];
		$mediumElement = self::findElementByXPath(
			$mediumEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.LongText4}"]/FormattedValue',
		);
		if ($mediumElement) {
			$mediumStr = trim($mediumElement);
			$paintingEn->setMedium($mediumStr);
		}
	}


	/* Signature */
	private static function inflateSignature(\SimpleXMLElement &$node,
	                                         Painting &$paintingDe,
	                                         Painting &$paintingEn) {
		/* de */
		$signatureDeSectionElement = $node->Section[20];
		$signatureElement = self::findElementByXPath(
			$signatureDeSectionElement,
			'Field[@FieldName="{OBJECTS.PaperSupport}"]/FormattedValue',
		);
		if ($signatureElement) {
			$signatureStr = trim($signatureElement);
			$paintingDe->setSignature($signatureStr);
		}

		/* en */
		$signatureEnSectionElement = $node->Section[21];
		$signatureElement = self::findElementByXPath(
			$signatureEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.ShortText6}"]/FormattedValue',
		);
		if ($signatureElement) {
			$signatureStr = trim($signatureElement);
			$paintingEn->setSignature($signatureStr);
		}
	}


	/* Inscription */
	private static function inflateInscription(\SimpleXMLElement &$node,
	                                           Painting &$paintingDe,
	                                           Painting &$paintingEn) {
		/* de */
		$inscriptionDeSectionElement = $node->Section[22];
		$inscriptionElement = self::findElementByXPath(
			$inscriptionDeSectionElement,
			'Field[@FieldName="{OBJECTS.Inscribed}"]/FormattedValue',
		);
		if ($inscriptionElement) {
			$inscriptionStr = trim($inscriptionElement);
			$paintingDe->setInscription($inscriptionStr);
		}

		/* en */
		$inscriptionEnSectionElement = $node->Section[23];
		$inscriptionElement = self::findElementByXPath(
			$inscriptionEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.LongText7}"]/FormattedValue',
		);
		if ($inscriptionElement) {
			$inscriptionStr = trim($inscriptionElement);
			$paintingEn->setInscription($inscriptionStr);
		}
	}


	/* Markings */
	private static function inflateMarkings(\SimpleXMLElement &$node,
	                                        Painting &$paintingDe,
	                                        Painting &$paintingEn) {
		/* de */
		$markingsDeSectionElement = $node->Section[24];
		$markingsElement = self::findElementByXPath(
			$markingsDeSectionElement,
			'Field[@FieldName="{OBJECTS.Markings}"]/FormattedValue',
		);
		if ($markingsElement) {
			$markingsStr = trim($markingsElement);
			$paintingDe->setMarkings($markingsStr);
		}

		/* en */
		$markingsEnSectionElement = $node->Section[25];
		$markingsElement = self::findElementByXPath(
			$markingsEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.LongText9}"]/FormattedValue',
		);
		if ($markingsElement) {
			$markingsStr = trim($markingsElement);
			$paintingEn->setMarkings($markingsStr);
		}
	}


	/* Related works */
	private static function inflateRelatedWorks(\SimpleXMLElement &$node,
	                                            Painting &$paintingDe,
	                                            Painting &$paintingEn) {
		/* de */
		$relatedWorksDeSectionElement = $node->Section[26];
		$relatedWorksElement = self::findElementByXPath(
			$relatedWorksDeSectionElement,
			'Field[@FieldName="{OBJECTS.RelatedWorks}"]/FormattedValue',
		);
		if ($relatedWorksElement) {
			$relatedWorksStr = trim($relatedWorksElement);
			$paintingDe->setRelatedWorks($relatedWorksStr);
		}

		/* en */
		$relatedWorksEnSectionElement = $node->Section[27];
		$relatedWorksElement = self::findElementByXPath(
			$relatedWorksEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.LongText6}"]/FormattedValue',
		);
		if ($relatedWorksElement) {
			$relatedWorksStr = trim($relatedWorksElement);
			$paintingEn->setRelatedWorks($relatedWorksStr);
		}
	}


	/* Exhibition history */
	private static function inflateExhibitionHistory(\SimpleXMLElement &$node,
	                                                 Painting &$paintingDe,
	                                                 Painting &$paintingEn) {
		/* de */
		$exhibitionHistoryDeSectionElement = $node->Section[28];
		$exhibitionHistoryElement = self::findElementByXPath(
			$exhibitionHistoryDeSectionElement,
			'Field[@FieldName="{OBJECTS.Exhibitions}"]/FormattedValue',
		);
		if ($exhibitionHistoryElement) {
			$exhibitionHistoryStr = trim($exhibitionHistoryElement);
			$cleanExhibitionHistoryStr = preg_replace(
				self::$inventoryNumberReplaceRegExpArr,
				'',
				$exhibitionHistoryStr,
			);
			$paintingDe->setExhibitionHistory($cleanExhibitionHistoryStr);
		}

		/* en */
		$exhibitionHistoryEnSectionElement = $node->Section[29];
		$exhibitionHistoryElement = self::findElementByXPath(
			$exhibitionHistoryEnSectionElement,
			'Field[@FieldName="{OBJCONTEXT.LongText8}"]/FormattedValue',
		);
		if ($exhibitionHistoryElement) {
			$exhibitionHistoryStr = trim($exhibitionHistoryElement);
			$cleanExhibitionHistoryStr = preg_replace(
				self::$inventoryNumberReplaceRegExpArr,
				'',
				$exhibitionHistoryStr,
			);
			$paintingEn->setExhibitionHistory($cleanExhibitionHistoryStr);
		}
	}


	/* Bibliography */
	private static function inflateBibliography(\SimpleXMLElement &$node,
	                                            Painting &$paintingDe,
	                                            Painting &$paintingEn) {
		$bibliographySectionElement = $node->Section[30];
		$bibliographyElement = self::findElementByXPath(
			$bibliographySectionElement,
			'Field[@FieldName="{OBJECTS.Bibliography}"]/FormattedValue',
		);
		if ($bibliographyElement) {
			$bibliographyStr = trim($bibliographyElement);
			$paintingDe->setBibliography($bibliographyStr);
			$paintingEn->setBibliography($bibliographyStr);
		}
	}


	/* References */
	private static function inflateReferences(\SimpleXMLElement &$node,
                                              Painting &$paintingDe,
	                                          Painting &$paintingEn) {
		$referenceDetailsElements = $node->Section[31]->Subreport->Details;

		for ($i = 0; $i < count($referenceDetailsElements); $i += 1) {
			$referenceDetailElement = $referenceDetailsElements[$i];

			if ($referenceDetailElement->count() === 0) {
				continue;
			}

			$reference = new ObjectReference;

			$paintingDe->addReference($reference);
			$paintingEn->addReference($reference);

			/* Text */
			$textElement = self::findElementByXPath(
				$referenceDetailElement,
				'Section[@SectionNumber="0"]/Text[@Name="Text5"]/TextValue',
			);
			if ($textElement) {
				$textStr = trim($textElement);
				$reference->setText($textStr);
			}

			/* Inventory number */
			$inventoryNumberElement = self::findElementByXPath(
				$referenceDetailElement,
				'Section[@SectionNumber="1"]/Field[@FieldName="{@Inventarnummer}"]/FormattedValue',
			);
			if ($inventoryNumberElement) {
				$inventoryNumberStr = trim($inventoryNumberElement);
				$reference->setInventoryNumber($inventoryNumberStr);
			}

			/* Remarks */
			$remarksElement = self::findElementByXPath(
				$referenceDetailElement,
				'Section[@SectionNumber="2"]/Field[@FieldName="{ASSOCIATIONS.Remarks}"]/FormattedValue',
			);
			if ($remarksElement) {
				$remarksStr = trim($remarksElement);
				$reference->setRemark($remarksStr);
			}
		}
	}


	/* Secondary References */
	private static function inflateSecondaryReferences(\SimpleXMLElement &$node,
                                                       Painting &$paintingDe,
	                                                   Painting &$paintingEn) {
		$referenceDetailsElements = $node->Section[32]->Subreport->Details;

		for ($i = 0; $i < count($referenceDetailsElements); $i += 1) {
			$referenceDetailElement = $referenceDetailsElements[$i];

			if ($referenceDetailElement->count() === 0) {
				continue;
			}

			$reference = new ObjectReference;

			$paintingDe->addSecondaryReference($reference);
			$paintingEn->addSecondaryReference($reference);

			/* Text */
			$textElement = self::findElementByXPath(
				$referenceDetailElement,
				'Section[@SectionNumber="0"]/Text[@Name="Text5"]/TextValue',
			);
			if ($textElement) {
				$textStr = trim($textElement);
				$reference->setText($textStr);
			}

			/* Inventory number */
			$inventoryNumberElement = self::findElementByXPath(
				$referenceDetailElement,
				'Section[@SectionNumber="1"]/Field[@FieldName="{@Inventarnummer}"]/FormattedValue',
			);
			if ($inventoryNumberElement) {
				$inventoryNumberStr = trim($inventoryNumberElement);
				$reference->setInventoryNumber($inventoryNumberStr);
			}

			/* Remarks */
			$remarksElement = self::findElementByXPath(
				$referenceDetailElement,
				'Section[@SectionNumber="2"]/Field[@FieldName="{ASSOCIATIONS.Remarks}"]/FormattedValue',
			);
			if ($remarksElement) {
				$remarksStr = trim($remarksElement);
				$reference->setRemark($remarksStr);
			}
		}
	}


	/* Additional text informations */
	private static function inflateAdditionalTextInformations(\SimpleXMLElement &$node,
	                                                          Painting &$paintingDe,
	                                                          Painting &$paintingEn) {
		$additionalTextsDetailsElements = $node->Section[33]->Subreport->Details;

		for ($i = 0; $i < count($additionalTextsDetailsElements); $i += 1) {
			$additionalTextDetailElement = $additionalTextsDetailsElements[$i];

			if ($additionalTextDetailElement->count() === 0) {
				continue;
			}

			$additionalTextInformation = new AdditionalTextInformation;

			/* Text type */
			$textTypeElement = self::findElementByXPath(
				$additionalTextDetailElement,
				'Section[@SectionNumber="0"]/Field[@FieldName="{TEXTTYPES.TextType}"]/FormattedValue',
			);

			/* Language determination */
			if ($textTypeElement) {
				$textTypeStr = trim($textTypeElement);
				$additionalTextInformation->setType($textTypeStr);

				if (self::$additionalTextLanguageTypes['de'] === $textTypeStr) {
					$paintingDe->addAdditionalTextInformation($additionalTextInformation);
				} else if (self::$additionalTextLanguageTypes['en'] === $textTypeStr) {
					$paintingEn->addAdditionalTextInformation($additionalTextInformation);
				} else if(self::$additionalTextLanguageTypes['author'] === $textTypeStr) { 
					$paintingDe->addAdditionalTextInformation($additionalTextInformation);
				} else if(self::$additionalTextLanguageTypes['letter'] === $textTypeStr) { 
					$paintingEn->addAdditionalTextInformation($additionalTextInformation);
				} else if(self::$additionalTextLanguageTypes['not_assigned'] === $textTypeStr) {
					echo 'Unassigned additional text type for object ' . $paintingDe->getInventoryNumber() . "\n";
					$paintingDe->addAdditionalTextInformation($additionalTextInformation);
					$paintingEn->addAdditionalTextInformation($additionalTextInformation);
				} else {
					echo 'Unknown additional text type: ' . $textTypeStr . ' for object ' . $paintingDe->getInventoryNumber() . "\n";
					$paintingDe->addAdditionalTextInformation($additionalTextInformation);
					$paintingEn->addAdditionalTextInformation($additionalTextInformation);
				}
			} else {
				$paintingDe->addAdditionalTextInformation($additionalTextInformation);
				$paintingEn->addAdditionalTextInformation($additionalTextInformation);
			}

			/* Text */
			$textElement = self::findElementByXPath(
				$additionalTextDetailElement,
				'Section[@SectionNumber="1"]/Field[@FieldName="{TEXTENTRIES.TextEntry}"]/FormattedValue',
			);
			if ($textElement) {
				$textStr = trim($textElement);
				$additionalTextInformation->setText($textStr);
			}

			/* Date */
			$dateElement = self::findElementByXPath(
				$additionalTextDetailElement,
				'Section[@SectionNumber="2"]/Text[@Name="Text21"]/TextValue',
			);
			if ($dateElement) {
				$dateStr = trim($dateElement);
				$additionalTextInformation->setDate($dateStr);
			}

			/* Year */
			$yearElement = self::findElementByXPath(
				$additionalTextDetailElement,
				'Section[@SectionNumber="3"]/Text[@Name="Text1"]/TextValue',
			);
			if ($yearElement) {
				$yearStr = trim($yearElement);
				$additionalTextInformation->setYear($yearStr);
			}

			/* Author */
			$authorElement = self::findElementByXPath(
				$additionalTextDetailElement,
				'Section[@SectionNumber="4"]/Text[@Name="Text3"]/TextValue',
			);
			if ($authorElement) {
				$authorStr = trim($authorElement);
				$additionalTextInformation->setAuthor($authorStr);
			}
		}
	}


	/* Publications */
	private static function inflatePublications(\SimpleXMLElement &$node,
	                                            Painting &$paintingDe,
	                                            Painting &$paintingEn) {
		$publicationDetailsElements = $node->Section[34]->Subreport->Details;

		for ($i = 0; $i < count($publicationDetailsElements); $i += 1) {
			$publicationDetailElement = $publicationDetailsElements[$i];

			if ($publicationDetailElement->count() === 0) {
				continue;
			}

			$publication = new Publication;

			$paintingDe->addPublication($publication);
			$paintingEn->addPublication($publication);

			/* Title */
			$titleElement = self::findElementByXPath(
				$publicationDetailElement,
				'Section[@SectionNumber="0"]/Field[@FieldName="{REFERENCEMASTER.Heading}"]/FormattedValue',
			);
			if ($titleElement) {
				$titleStr = trim($titleElement);
				$publication->setTitle($titleStr);
			}

			/* Pagenumber */
			$pageNumberElement = self::findElementByXPath(
				$publicationDetailElement,
				'Section[@SectionNumber="1"]/Field[@FieldName="{REFXREFS.PageNumber}"]/FormattedValue',
			);
			if ($pageNumberElement) {
				$pageNumberStr = trim($pageNumberElement);
				$publication->setPageNumber($pageNumberStr);
			}

			/* Reference */
			$referenceIdElement = self::findElementByXPath(
				$publicationDetailElement,
				'Section[@SectionNumber="2"]/Field[@FieldName="{REFERENCEMASTER.ReferenceID}"]/FormattedValue',
			);
			if ($referenceIdElement) {
				$referenceIdStr = trim($referenceIdElement);
				$publication->setReferenceId($referenceIdStr);
			}
		}
	}


	/* Keywords */
	private static function inflateKeywords(\SimpleXMLElement &$node,
	                                        Painting &$paintingDe,
	                                        Painting &$paintingEn) {
		$keywordDetailsElements = $node->Section[35]->Subreport->Details;

		for ($i = 0; $i < count($keywordDetailsElements); $i += 1) {
			$keywordDetailElement = $keywordDetailsElements[$i];

			if ($keywordDetailElement->count() === 0) {
				continue;
			}

			$metaReference = new MetaReference;

			/* Type */
			$keywordTypeElement = self::findElementByXPath(
				$keywordDetailElement,
				'Section[@SectionNumber="0"]/Field[@FieldName="{THESXREFTYPES.ThesXrefType}"]/FormattedValue',
			);
			if ($keywordTypeElement) {
				$keywordTypeStr = trim($keywordTypeElement);
				$metaReference->setType($keywordTypeStr);
			}

			/* Term */
			$keywordTermElement = self::findElementByXPath(
				$keywordDetailElement,
				'Section[@SectionNumber="1"]/Field[@FieldName="{TERMS.Term}"]/FormattedValue',
			);
			if ($keywordTermElement) {
				$keywordTermStr = trim($keywordTermElement);
				$metaReference->setTerm($keywordTermStr);
			}

			/* Path */
			$keywordPathElement = self::findElementByXPath(
				$keywordDetailElement,
				'Section[@SectionNumber="3"]/Field[@FieldName="{THESXREFSPATH1.Path}"]/FormattedValue',
			);
			if ($keywordPathElement) {
				$keywordPathStr = trim($keywordPathElement);
				$metaReference->setPath($keywordPathStr);
			}


			/* Decide if keyword is valid */
			if (!empty($metaReference->getTerm())) {
				$paintingDe->addKeyword($metaReference);
				$paintingEn->addKeyword($metaReference);
			}
		}
	}


	/* Locations */
	private static function inflateLocations(\SimpleXMLElement &$node,
	                                         Painting &$paintingDe,
	                                         Painting &$paintingEn) {
		$locationDetailsElements = $node->Section[36]->Subreport->Details;

		for ($i = 0; $i < count($locationDetailsElements); $i += 1) {
			$locationDetailElement = $locationDetailsElements[$i];

			if ($locationDetailElement->count() === 0) {
				continue;
			}

			$metaReference = new MetaReference;

			/* Type */
			$locationTypeElement = self::findElementByXPath(
				$locationDetailElement,
				'Section[@SectionNumber="0"]/Field[@FieldName="{THESXREFTYPES.ThesXrefType}"]/FormattedValue',
			);

			/* Language determination */
			if ($locationTypeElement) {
				$locationTypeStr = trim($locationTypeElement);
				$metaReference->setType($locationTypeStr);

				if (self::$locationLanguageTypes['de'] === $locationTypeStr) {
					$paintingDe->addLocation($metaReference);
				} else if (self::$locationLanguageTypes['en'] === $locationTypeStr) {
					$paintingEn->addLocation($metaReference);
				} else if(self::$locationLanguageTypes['not_assigned'] === $locationTypeStr) {
					echo 'Unassigned location type for object ' . $graphicDe->getInventoryNumber() . "\n";
					$paintingDe->addLocation($metaReference);
					$paintingEn->addLocation($metaReference);
				} else {
					echo 'Unknown location type: ' . $textTypeStr . ' for object ' . $graphicDe->getInventoryNumber() . "\n";
					$paintingDe->addLocation($metaReference);
					$paintingEn->addLocation($metaReference);
				}
			} else {
				$paintingDe->addLocation($metaReference);
				$paintingEn->addLocation($metaReference);
			}

			/* Term */
			$locationTermElement = self::findElementByXPath(
				$locationDetailElement,
				'Section[@SectionNumber="1"]/Field[@FieldName="{TERMS.Term}"]/FormattedValue',
			);
			if ($locationTermElement) {
				$locationTermStr = trim($locationTermElement);
				$metaReference->setTerm($locationTermStr);
			}

			/* Path */
			$locationPathElement = self::findElementByXPath(
				$locationDetailElement,
				'Section[@SectionNumber="3"]/Field[@FieldName="{THESXREFSPATH1.Path}"]/FormattedValue',
			);
			if ($locationPathElement) {
				$locationPathStr = trim($locationPathElement);
				$metaReference->setPath($locationPathStr);
			}
		}
	}


	/* Repository */
	private static function inflateRepository(\SimpleXMLElement &$node,
	                                          Painting &$paintingDe,
	                                          Painting &$paintingEn) {
		$repositoryDetailsSubreport = $node->Section[37]->Subreport;

		// de
		$repositoryDeElement = self::findElementByXPath(
			$repositoryDetailsSubreport,
			'Details[1]/Section[@SectionNumber="3"]/Field[@FieldName="{CONALTNAMES.DisplayName}"]/FormattedValue',
		);
		if ($repositoryDeElement) {
			$repositoryStr = trim($repositoryDeElement);

			$paintingDe->setRepository($repositoryStr);
		}

		// en
		$repositoryEnElement = self::findElementByXPath(
			$repositoryDetailsSubreport,
			'Details[2]/Section[@SectionNumber="3"]/Field[@FieldName="{CONALTNAMES.DisplayName}"]/FormattedValue',
		);
		if ($repositoryEnElement) {
			$repositoryStr = trim($repositoryEnElement);

			$paintingEn->setRepository($repositoryStr);
		}
	}


	/* Owner */
	private static function inflateOwner(\SimpleXMLElement &$node,
	                                          Painting &$paintingDe,
	                                          Painting &$paintingEn) {
		$ownerDetailsSubreport = $node->Section[37]->Subreport;

		// de
		$ownerDeElement = self::findElementByXPath(
			$ownerDetailsSubreport,
			'Details[3]/Section[@SectionNumber="3"]/Field[@FieldName="{CONALTNAMES.DisplayName}"]/FormattedValue',
		);
		if ($ownerDeElement) {
			$ownerStr = trim($ownerDeElement);

			$paintingDe->setOwner($ownerStr);
		}

		// en
		$ownerEnElement = self::findElementByXPath(
			$ownerDetailsSubreport,
			'Details[4]/Section[@SectionNumber="3"]/Field[@FieldName="{CONALTNAMES.DisplayName}"]/FormattedValue',
		);
		if ($ownerEnElement) {
			$ownerStr = trim($ownerEnElement);

			$paintingEn->setOwner($ownerStr);
		}
	}


	/* Sorting number */
	private static function inflateSortingNumber(\SimpleXMLElement &$node,
	                                             Painting &$paintingDe,
	                                             Painting &$paintingEn) {
		$sortingNumberSubreport = $node->Section[38];

		$sortingNumberElement = self::findElementByXPath(
			$sortingNumberSubreport,
			'Field[@FieldName="{OBJCONTEXT.Period}"]/FormattedValue',
		);
		if ($sortingNumberElement) {
			$sortingNumberStr = trim($sortingNumberElement);

			$paintingDe->setSortingNumber($sortingNumberStr);
			$paintingEn->setSortingNumber($sortingNumberStr);
		}
	}


	/* Catalog work reference */
	private static function inflateCatalogWorkReference(\SimpleXMLElement &$node,
	                                                    Painting &$paintingDe,
	                                                    Painting &$paintingEn) {
		$catalogWorkReferenceDetailsElements = $node->Section[39]->Subreport->Details;

		for ($i = 0; $i < count($catalogWorkReferenceDetailsElements); $i += 1) {
			$catalogWorkReferenceDetailElement = $catalogWorkReferenceDetailsElements[$i];

			if ($catalogWorkReferenceDetailElement->count() === 0) {
				continue;
			}

			$catalogWorkReference = new CatalogWorkReference;

			/* Description */
			$descriptionElement = self::findElementByXPath(
				$catalogWorkReferenceDetailElement,
				'Field[@FieldName="{AltNumDescriptions.AltNumDescription}"]/FormattedValue',
			);
			if ($descriptionElement) {
				$descriptionStr = trim($descriptionElement);

				$catalogWorkReference->setDescription($descriptionStr);
			}

			/* Reference number */
			$referenceNumberElement = self::findElementByXPath(
				$catalogWorkReferenceDetailElement,
				'Field[@FieldName="{AltNums.AltNum}"]/FormattedValue',
			);
			if ($referenceNumberElement) {
				$referenceNumberStr = trim($referenceNumberElement);

				$catalogWorkReference->setReferenceNumber($referenceNumberStr);
			}

			/* Remarks */
			$remarksElement = self::findElementByXPath(
				$catalogWorkReferenceDetailElement,
				'Field[@FieldName="{AltNums.Remarks}"]/FormattedValue',
			);
			if ($remarksElement) {
				$remarksStr = trim($remarksElement);

				$catalogWorkReference->setRemarks($remarksStr);
			}


			/* Decide if reference should be added */
			if (!empty($catalogWorkReference->getReferenceNumber())) {
				$paintingDe->addCatalogWorkReference($catalogWorkReference);
				$paintingEn->addCatalogWorkReference($catalogWorkReference);
			}
		}
	}


	/* Structured dimension */
	private static function inflateStructuredDimension(\SimpleXMLElement &$node,
	                                                   Painting &$paintingDe,
	                                                   Painting &$paintingEn) {
		$catalogWorkReferenceSubreport = $node->Section[40]->Subreport;

		$structuredDimension = new StructuredDimension;

		$paintingDe->setStructuredDimension($structuredDimension);
		$paintingEn->setStructuredDimension($structuredDimension);

		/* element */
		$elementElement = self::findElementByXPath(
			$catalogWorkReferenceSubreport,
			'Field[@FieldName="{DIMENSIONELEMENTS.Element}"]/FormattedValue',
		);

		if($elementElement) {
			$elementStr = trim($elementElement);

			$structuredDimension->setElement($elementStr);
		}


		/* Details elements */
		$detailsElements = self::findElementsByXPath(
			$catalogWorkReferenceSubreport,
			'Details',
		);
		if (count($detailsElements) === 2) {
			/* height */
			$heightElement = self::findElementByXPath(
				$detailsElements[0],
				'Field[@FieldName="{DIMENSIONS.Dimension}"]/Value',
			);
			if ($heightElement) {
				$heightNumber = trim($heightElement);

				$structuredDimension->setHeight($heightNumber);
			}

			/* width */
			$widthElement = self::findElementByXPath(
				$detailsElements[1],
				'Field[@FieldName="{DIMENSIONS.Dimension}"]/Value',
			);
			if ($widthElement) {
				$widthNumber = trim($widthElement);

				$structuredDimension->setWidth($widthNumber);
			}
		}

	}


	/* Structured dimension */
	private static function inflateIsBestOf(\SimpleXMLElement &$node,
	                                        Painting &$paintingDe,
	                                        Painting &$paintingEn) {
		$isBestOf = isset($node->Section[41]);

		$paintingDe->setIsBestOf($isBestOf);
		$paintingEn->setIsBestOf($isBestOf);
	}


	private static function registerXPathNamespace(\SimpleXMLElement $node) {
		$node->registerXPathNamespace(self::$nsPrefix, self::$ns);
	}


	private static function findElementsByXPath(\SimpleXMLElement $node, string $path) {
		self::registerXPathNamespace($node);

		$splitPath = explode('/', $path);

		$nsPrefix = self::$nsPrefix;
		$xpathStr = './/' . implode('/', array_map(
			function($val) use($nsPrefix) {
				return empty($val) ? $val : $nsPrefix . ':' . $val;
			},
			$splitPath
		));

		return $node->xpath($xpathStr);
	}


	private static function findElementByXPath(\SimpleXMLElement $node, string $path) {
		$result = self::findElementsByXPath($node, $path);

		if (is_array($result) && count($result) > 0) {
			return $result[0];
		}

		return FALSE;
	}


	/*
	  TODO: Move out into helper -> dynamically settable at runtime if possible
	    -> composition over inheritance
	*/
	private static function splitLanguageString(string $langStr): array {
		$splitLangStrs = array_map('trim', explode(self::$langSplitChar, $langStr));
		$cntItems = count($splitLangStrs);

		if ($cntItems > 0 && $cntItems < 2) {
			$splitLangStrs[] = $splitLangStrs[0];
		} 

		return $splitLangStrs;
	}

}