<?php
/**
 * Created by IntelliJ IDEA.
 * User: Prog1
 * Date: 9/19/2014
 * Time: 5:09 PM
 */

namespace Zone\Core\Component\PrintContent;


use Anouar\Fpdf\Fpdf;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zone\PM\Events\PrinterEvents;

abstract class BasePrinter extends Fpdf
{
    private $javascript = "";
    private $n_js;
    private $useAutoPrint = false;
    private $fileName = 'Document.pdf';
    private $docTitle = '';
    private $return = false;
    private $eventDispatcher;

    function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        $this->eventDispatcher = new EventDispatcher();
        parent::__construct($orientation = 'P', $unit = 'mm', $size = 'A4');
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    function setDocumentTitle($title)
    {
        $this->docTitle = $title;
        $this->fileName = $this->slug(str_replace(' ', '_', $this->docTitle)) . '.pdf';
    }

    function getDocumentTitle()
    {
        return $this->docTitle;
    }

    function useAutoPrint()
    {
        $this->javascript = "print('true');";
        $this->useAutoPrint = true;
    }

    function useReturn()
    {
        $this->return = true;
    }

    function isAutoPrint()
    {
        return $this->useAutoPrint;
    }

    function getFileName()
    {
        return $this->fileName;
    }

    function Output()
    {
        if ($this->return) return parent::Output('', 'S');
        if ($this->isAutoPrint()) {
            parent::Output();
        } else {
            parent::Output($this->getFileName(), 'D');
        }
        return false;
    }

    function escape($text)
    {
        return html_entity_decode($text, ENT_QUOTES, "utf-8");
    }

    function _putjavascript()
    {
        $this->_newobj();
        $this->n_js = $this->n;
        $this->_out('<<');
        $this->_out('/Names [(EmbeddedJS) ' . ($this->n + 1) . ' 0 R]');
        $this->_out('>>');
        $this->_out('endobj');
        $this->_newobj();
        $this->_out('<<');
        $this->_out('/S /JavaScript');
        $this->_out('/JS ' . $this->_textstring($this->javascript));
        $this->_out('>>');
        $this->_out('endobj');
    }

    function _putresources()
    {
        $this->_putextgstates();
        parent::_putresources();
        if (!empty($this->javascript)) {
            $this->_putjavascript();
        }
    }

    function _putcatalog()
    {
        parent::_putcatalog();
        if (!empty($this->javascript)) {
            $this->_out('/Names <</JavaScript ' . ($this->n_js) . ' 0 R>>');
        }
    }

    static function slug($input)
    {
        $string = html_entity_decode($input, ENT_COMPAT, "UTF-8");
        $oldLocale = setlocale(LC_ALL, 'fr_FR');
        setlocale(LC_CTYPE, 'en_US.UTF-8');
        $string = iconv("UTF-8", "ASCII//TRANSLIT", $string);
        setlocale(LC_CTYPE, $oldLocale);
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $string));
    }

    //Cell with horizontal scaling if text is too wide
    function CellFit($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $scale = 1, $force = 0)
    {
        //Get string width
        $str_width = $this->GetStringWidth($txt);
        $str_width = $str_width == 0 ?: $str_width;
        //Calculate ratio to fit cell
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $ratio = ($w - $this->cMargin * 2) / $str_width;

        $fit = ($ratio < 1 || ($ratio > 1 && $force == 1));
        if ($fit) {
            switch ($scale) {

                //Character spacing
                case 0:
                    //Calculate character spacing in points
                    $char_space = ($w - $this->cMargin * 2 - $str_width) / max($this->MBGetStringLength($txt) - 1, 1) * $this->k;
                    //Set character spacing
                    $this->_out(sprintf('BT %.2f Tc ET', $char_space));
                    break;

                //Horizontal scaling
                case 1:
                    //Calculate horizontal scaling
                    $horiz_scale = $ratio * 100.0;
                    //Set horizontal scaling
                    $this->_out(sprintf('BT %.2f Tz ET', $horiz_scale));
                    break;

            }
            //Override user alignment (since text will fill up cell)
            $align = '';
        }

        //Pass on to Cell method
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);

        //Reset character spacing/horizontal scaling
        if ($fit)
            $this->_out('BT ' . ($scale == 0 ? '0 Tc' : '100 Tz') . ' ET');
    }

    //Cell with horizontal scaling only if necessary
    function CellFitScale($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, 1, 0);
    }

    //Cell with horizontal scaling always
    function CellFitScaleForce($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, 1, 1);
    }

    //Cell with character spacing only if necessary
    function CellFitSpace($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, 0, 0);
    }

    //Cell with character spacing always
    function CellFitSpaceForce($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '')
    {
        //Same as calling CellFit directly
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, 0, 1);
    }

    //Patch to also work with CJK double-byte text
    function MBGetStringLength($s)
    {
        if ($this->CurrentFont['type'] == 'Type0') {
            $len = 0;
            $nbbytes = strlen($s);
            for ($i = 0; $i < $nbbytes; $i++) {
                if (ord($s[$i]) < 128)
                    $len++;
                else {
                    $len++;
                    $i++;
                }
            }
            return $len;
        } else
            return strlen($s);
    }

    function toUtf($txt)
    {
        return iconv('UTF-8', 'windows-1252', stripslashes(trim($this->escape($txt))));
    }

    var $extgstates = array();

    // alpha: real value from 0 (transparent) to 1 (opaque)
    // bm:    blend mode, one of the following:
    //          Normal, Multiply, Screen, Overlay, Darken, Lighten, ColorDodge, ColorBurn,
    //          HardLight, SoftLight, Difference, Exclusion, Hue, Saturation, Color, Luminosity
    function SetAlpha($alpha, $bm = 'Normal')
    {
        // set alpha for stroking (CA) and non-stroking (ca) operations
        $gs = $this->AddExtGState(array('ca' => $alpha, 'CA' => $alpha, 'BM' => '/' . $bm));
        $this->SetExtGState($gs);
    }

    function AddExtGState($parms)
    {
        $n = count($this->extgstates) + 1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    function SetExtGState($gs)
    {
        $this->_out(sprintf('/GS%d gs', $gs));
    }

    function _enddoc()
    {
        if (!empty($this->extgstates) && $this->PDFVersion < '1.4')
            $this->PDFVersion = '1.4';
        parent::_enddoc();
    }

    function _putextgstates()
    {
        for ($i = 1; $i <= count($this->extgstates); $i++) {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_out('<</Type /ExtGState');
            $parms = $this->extgstates[$i]['parms'];
            $this->_out(sprintf('/ca %.3F', $parms['ca']));
            $this->_out(sprintf('/CA %.3F', $parms['CA']));
            $this->_out('/BM ' . $parms['BM']);
            $this->_out('>>');
            $this->_out('endobj');
        }
    }

    function _putresourcedict()
    {
        parent::_putresourcedict();
        $this->_out('/ExtGState <<');
        foreach ($this->extgstates as $k => $extgstate)
            $this->_out('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
        $this->_out('>>');
    }

    function AddPage($orientation = '', $size = '')
    {
        parent::AddPage($orientation, $size);
        $this->getEventDispatcher()->dispatch(PrinterEvents::ADD_PAGE, new PrinterEvents($this));
    }
}