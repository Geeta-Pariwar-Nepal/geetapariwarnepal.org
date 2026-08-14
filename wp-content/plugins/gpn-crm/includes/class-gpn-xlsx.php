<?php
/**
 * GPN CRM - minimal XLSX reader/writer (pure PHP, no dependencies).
 *
 * Uses PHP's built-in ZipArchive to produce/consume Excel OpenXML files.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPN_Xlsx {

	/**
	 * Build a single-sheet XLSX file path from a 2D array of values.
	 */
	public static function write( $path, array $rows ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		// Shared strings table.
		$shared = array();
		$index  = array();
		$data   = array();
		foreach ( $rows as $r ) {
			$line = array();
			foreach ( $r as $v ) {
				$v = (string) $v;
				if ( ! isset( $index[ $v ] ) ) {
					$index[ $v ] = count( $shared );
					$shared[]    = $v;
				}
				$line[] = $index[ $v ];
			}
			$data[] = $line;
		}

		$sheet = self::sheet_xml( $data, count( $shared ) );

		$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

		$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Sadhaks" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

		$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

		$ss = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $shared ) . '" uniqueCount="' . count( $shared ) . '">';
		foreach ( $shared as $s ) {
			$ss .= '<si><t xml:space="preserve">' . self::xml_escape( $s ) . '</t></si>';
		}
		$ss .= '</sst>';

		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F6FEB"/><bgColor indexed="64"/></patternFill></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>
</styleSheet>';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$zip->addFromString( '[Content_Types].xml', $content_types );
		$zip->addFromString( '_rels/.rels', $rels );
		$zip->addFromString( 'xl/workbook.xml', $workbook );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
		$zip->addFromString( 'xl/sharedStrings.xml', $ss );
		$zip->addFromString( 'xl/styles.xml', $styles );
		$zip->close();
		return true;
	}

	private static function sheet_xml( array $data, $shared_count ) {
		$max = 0;
		foreach ( $data as $row ) {
			$max = max( $max, count( $row ) );
		}
		$col = max( $max, 1 );

		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetViews><sheetView workbookViewId="0"/></sheetViews>
<sheetData>';
		foreach ( $data as $ri => $row ) {
			$xml .= '<row r="' . ( $ri + 1 ) . '">';
			$style = ( 0 === $ri ) ? ' s="1"' : '';
			foreach ( $row as $ci => $si ) {
				$ref = self::col_letter( $ci ) . ( $ri + 1 );
				$xml .= '<c r="' . $ref . '"' . $style . ' t="s"><v>' . (int) $si . '</v></c>';
			}
			$xml .= '</row>';
		}
		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	private static function col_letter( $index ) {
		$letter = '';
		$index++;
		while ( $index > 0 ) {
			$mod    = ( $index - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$index  = intdiv( $index - $mod, 26 );
		}
		return $letter;
	}

	private static function xml_escape( $s ) {
		return htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Read a single-sheet XLSX into a 2D array. Returns false on failure.
	 */
	public static function read( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return false;
		}
		$sheet      = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$shared_raw = $zip->getFromName( 'xl/sharedStrings.xml' );
		$zip->close();
		if ( false === $sheet ) {
			return false;
		}

		$shared = array();
		if ( false !== $shared_raw ) {
			$doc = new DOMDocument();
			libxml_use_internal_errors( true );
			$doc->loadXML( $shared_raw );
			libxml_clear_errors();
			foreach ( $doc->getElementsByTagName( 'si' ) as $si ) {
				$shared[] = $si->textContent;
			}
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadXML( $sheet );
		libxml_clear_errors();

		$rows = array();
		foreach ( $doc->getElementsByTagName( 'row' ) as $row ) {
			$cells = array();
			foreach ( $row->childNodes as $node ) {
				if ( $node->nodeType === XML_ELEMENT_NODE && 'c' === $node->nodeName ) {
					$t     = $node->getAttribute( 't' );
					$vnode = $node->getElementsByTagName( 'v' )->item( 0 );
					$is    = $node->getElementsByTagName( 'is' )->item( 0 );
					if ( 's' === $t && $vnode ) {
						$cells[] = isset( $shared[ (int) $vnode->textContent ] ) ? $shared[ (int) $vnode->textContent ] : '';
					} elseif ( $is ) {
						$cells[] = $is->textContent;
					} elseif ( $vnode ) {
						$cells[] = $vnode->textContent;
					} else {
						$cells[] = '';
					}
				}
			}
			$rows[] = $cells;
		}
		return $rows;
	}
}
