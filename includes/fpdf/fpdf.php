<?php
/*******************************************************************************
* FPDF                                                                         *
*                                                                              *
* Version: 1.85                                                               *
* Date:    2021-08-28                                                         *
* Author:  Olivier PLATHEY                                                    *
*******************************************************************************/

define('FPDF_VERSION','1.85');

class FPDF
{
    protected $page;               // current page number
    protected $n;                  // current object number
    protected $offsets;            // array of object offsets
    protected $buffer;             // buffer holding in-memory PDF
    protected $pages;              // array containing pages
    protected $state;              // current document state
    protected $compress;           // compression flag
    protected $k;                  // scale factor (number of points in user unit)
    protected $DefOrientation;     // default orientation
    protected $CurOrientation;     // current orientation
    protected $StdPageSizes;       // standard page sizes
    protected $DefPageSize;        // default page size
    protected $CurPageSize;        // current page size
    protected $CurRotation;        // current page rotation
    protected $PageInfo;           // page-related data
    protected $wPt, $hPt;         // dimensions of current page in points
    protected $w, $h;             // dimensions of current page in user unit
    protected $lMargin;           // left margin
    protected $tMargin;           // top margin
    protected $rMargin;           // right margin
    protected $bMargin;           // page break margin
    protected $cMargin;           // cell margin
    protected $x, $y;             // current position in user unit
    protected $lasth;             // height of last printed cell
    protected $LineWidth;         // line width in user unit
    protected $fontpath;          // path containing fonts
    protected $CoreFonts;         // array of core font names
    protected $fonts;             // array of used fonts
    protected $FontFiles;         // array of font files
    protected $encodings;         // array of encodings
    protected $cmaps;             // array of ToUnicode CMaps
    protected $FontFamily;        // current font family
    protected $FontStyle;         // current font style
    protected $underline;         // underlining flag
    protected $FontSizePt;        // current font size in points
    protected $FontSize;          // current font size in user unit
    protected $DrawColor;         // commands for drawing color
    protected $FillColor;         // commands for filling color
    protected $TextColor;         // commands for text color
    protected $ColorFlag;         // indicates whether fill and text colors are different
    protected $WithAlpha;         // indicates whether alpha channel is used
    protected $ws;                // word spacing
    protected $images;            // array of used images
    protected $PageLinks;         // array of links in pages
    protected $links;             // array of internal links
    protected $AutoPageBreak;     // automatic page breaking
    protected $PageBreakTrigger;  // threshold used to trigger page breaks
    protected $InHeader;          // flag set when processing header
    protected $InFooter;          // flag set when processing footer
    protected $AliasNbPages;      // alias for total number of pages
    protected $ZoomMode;          // zoom display mode
    protected $LayoutMode;        // layout display mode
    protected $metadata;          // document properties
    protected $PDFVersion;        // PDF version number

    function __construct($orientation='P', $unit='mm', $size='A4')
    {
        // Some checks
        $this->_dochecks();
        // Initialization of properties
        $this->state = 0;
        $this->page = 0;
        $this->n = 2;
        $this->buffer = '';
        $this->pages = array();
        $this->PageInfo = array();
        $this->fonts = array();
        $this->FontFiles = array();
        $this->encodings = array();
        $this->cmaps = array();
        $this->images = array();
        $this->links = array();
        $this->InHeader = false;
        $this->InFooter = false;
        $this->lasth = 0;
        $this->FontFamily = '';
        $this->FontStyle = '';
        $this->FontSizePt = 12;
        $this->underline = false;
        $this->DrawColor = '0 G';
        $this->FillColor = '0 g';
        $this->TextColor = '0 g';
        $this->ColorFlag = false;
        $this->WithAlpha = false;
        $this->ws = 0;
        
        // Font path
        if(defined('FPDF_FONTPATH'))
        {
            $this->fontpath = FPDF_FONTPATH;
            if(substr($this->fontpath,-1)!='/' && substr($this->fontpath,-1)!='\\')
                $this->fontpath .= '/';
        }
        elseif(is_dir(dirname(__FILE__).'/font'))
            $this->fontpath = dirname(__FILE__).'/font/';
        else
            $this->fontpath = '';
        
        // Core fonts
        $this->CoreFonts = array('courier', 'helvetica', 'times', 'symbol', 'zapfdingbats');
        
        // Scale factor
        if($unit=='pt')
            $this->k = 1;
        elseif($unit=='mm')
            $this->k = 72/25.4;
        elseif($unit=='cm')
            $this->k = 72/2.54;
        elseif($unit=='in')
            $this->k = 72;
        else
            $this->Error('Incorrect unit: '.$unit);
        
        // Page sizes
        $this->StdPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28),
            'letter'=>array(612,792), 'legal'=>array(612,1008));
        
        if(is_string($size))
            $size = $this->_getpagesize($size);
        $this->DefPageSize = $size;
        $this->CurPageSize = $size;
        
        // Page orientation
        $orientation = strtolower($orientation);
        if($orientation=='p' || $orientation=='portrait')
        {
            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        }
        elseif($orientation=='l' || $orientation=='landscape')
        {
            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        }
        else
            $this->Error('Incorrect orientation: '.$orientation);
        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w*$this->k;
        $this->hPt = $this->h*$this->k;
        
        // Page rotation
        $this->CurRotation = 0;
        
        // Page margins (1 cm)
        $margin = 28.35/$this->k;
        $this->SetMargins($margin,$margin);
        
        // Interior cell margin (1 mm)
        $this->cMargin = $margin/10;
        
        // Line width (0.2 mm)
        $this->LineWidth = .567/$this->k;
        
        // Automatic page break
        $this->SetAutoPageBreak(true,2*$margin);
        
        // Default display mode
        $this->SetDisplayMode('default');
        
        // Enable compression
        $this->SetCompression(true);
        
        // Set default PDF version number
        $this->PDFVersion = '1.3';
        
        // Set default font
        $this->SetFont('helvetica', '', 12);
        
        // Initialize metadata array
        $this->metadata = array();
    }

    function SetMargins($left, $top, $right=null)
    {
        // Set left, top and right margins
        $this->lMargin = $left;
        $this->tMargin = $top;
        if($right===null)
            $right = $left;
        $this->rMargin = $right;
    }

    function SetLeftMargin($margin)
    {
        // Set left margin
        $this->lMargin = $margin;
        if($this->page>0 && $this->x<$margin)
            $this->x = $margin;
    }

    function SetTopMargin($margin)
    {
        // Set top margin
        $this->tMargin = $margin;
    }

    function SetRightMargin($margin)
    {
        // Set right margin
        $this->rMargin = $margin;
    }

    function SetAutoPageBreak($auto, $margin=0)
    {
        // Set auto page break mode and triggering margin
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h-$margin;
    }

    function SetDisplayMode($zoom, $layout='default')
    {
        // Set display mode in viewer
        if($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
            $this->ZoomMode = $zoom;
        else
            $this->Error('Incorrect zoom display mode: '.$zoom);
        if($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
            $this->LayoutMode = $layout;
        else
            $this->Error('Incorrect layout display mode: '.$layout);
    }

    function SetCompression($compress)
    {
        // Set page compression
        if(function_exists('gzcompress'))
            $this->compress = $compress;
        else
            $this->compress = false;
    }

    function AddPage($orientation='', $size='', $rotation=0)
    {
        // Start a new page
        if($this->state==3)
            $this->Error('The document is closed');
        $family = $this->FontFamily;
        $style = $this->FontStyle.($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;
        if($this->page>0)
        {
            // Page footer
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            // Close page
            $this->_endpage();
        }
        // Start new page
        $this->_beginpage($orientation,$size,$rotation);
        // Set line cap style to square
        $this->_out('2 J');
        // Set line width
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w',$lw*$this->k));
        // Set font
        if($family)
            $this->SetFont($family,$style,$fontsize);
        // Set colors
        $this->DrawColor = $dc;
        if($dc!='0 G')
            $this->_out($dc);
        $this->FillColor = $fc;
        if($fc!='0 g')
            $this->_out($fc);
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
        // Page header
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;
        // Restore line width
        if($this->LineWidth!=$lw)
        {
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w',$lw*$this->k));
        }
        // Restore font
        if($family)
            $this->SetFont($family,$style,$fontsize);
        // Restore colors
        if($this->DrawColor!=$dc)
        {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if($this->FillColor!=$fc)
        {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
    }

    function Header()
    {
        // To be implemented in your own inherited class
    }

    function Footer()
    {
        // To be implemented in your own inherited class
    }

    protected function _loadfont($font)
    {
        // Load a core font
        if($font=='arial')
            $font = 'helvetica';
        $fontkey = $font;
        if(!isset($this->fonts[$fontkey]))
        {
            $name = $font;
            if($name=='times')
                $name = 'Times-Roman';
            if($name=='helvetica')
                $name = 'Helvetica';
            if($name=='courier')
                $name = 'Courier';
            if($name=='symbol')
                $name = 'Symbol';
            if($name=='zapfdingbats')
                $name = 'ZapfDingbats';
                
            // Create character width array for core fonts
            $cw = array();
            if($name == 'Helvetica') {
                // Helvetica character widths (simplified)
                for($i=0;$i<=255;$i++) {
                    if($i >= 32 && $i <= 126) {
                        $cw[chr($i)] = 556; // Average width for Helvetica
                    } else {
                        $cw[chr($i)] = 278;
                    }
                }
                // Specific widths for common characters
                $cw[' '] = 278;
                $cw['!'] = 278;
                $cw['"'] = 355;
                $cw['#'] = 556;
                $cw['$'] = 556;
                $cw['%'] = 889;
                $cw['&'] = 667;
                $cw["'"] = 191;
                $cw['('] = 333;
                $cw[')'] = 333;
                $cw['*'] = 389;
                $cw['+'] = 584;
                $cw[','] = 278;
                $cw['-'] = 333;
                $cw['.'] = 278;
                $cw['/'] = 278;
                // Numbers
                for($i = 48; $i <= 57; $i++) {
                    $cw[chr($i)] = 556;
                }
                // Letters
                $cw['A'] = 667; $cw['B'] = 667; $cw['C'] = 722; $cw['D'] = 722;
                $cw['E'] = 667; $cw['F'] = 611; $cw['G'] = 778; $cw['H'] = 722;
                $cw['I'] = 278; $cw['J'] = 500; $cw['K'] = 667; $cw['L'] = 556;
                $cw['M'] = 833; $cw['N'] = 722; $cw['O'] = 778; $cw['P'] = 667;
                $cw['Q'] = 778; $cw['R'] = 722; $cw['S'] = 667; $cw['T'] = 611;
                $cw['U'] = 722; $cw['V'] = 667; $cw['W'] = 944; $cw['X'] = 667;
                $cw['Y'] = 667; $cw['Z'] = 611;
                // Lowercase
                $cw['a'] = 556; $cw['b'] = 556; $cw['c'] = 500; $cw['d'] = 556;
                $cw['e'] = 556; $cw['f'] = 278; $cw['g'] = 556; $cw['h'] = 556;
                $cw['i'] = 222; $cw['j'] = 222; $cw['k'] = 500; $cw['l'] = 222;
                $cw['m'] = 833; $cw['n'] = 556; $cw['o'] = 556; $cw['p'] = 556;
                $cw['q'] = 556; $cw['r'] = 333; $cw['s'] = 500; $cw['t'] = 278;
                $cw['u'] = 556; $cw['v'] = 500; $cw['w'] = 722; $cw['x'] = 500;
                $cw['y'] = 500; $cw['z'] = 500;
            } else {
                // Default widths for other fonts
                for($i=0;$i<=255;$i++)
                    $cw[chr($i)] = 600;
            }
            
            $this->fonts[$fontkey] = array(
                'i' => count($this->fonts)+1,
                'type' => 'core',
                'name' => $name,
                'up' => -100,
                'ut' => 50,
                'cw' => $cw
            );
        }
    }

    function SetFont($family, $style='', $size=0)
    {
        // Select a font; size given in points
        if($family=='')
            $family = $this->FontFamily;
        else
            $family = strtolower($family);
        $style = strtoupper($style);
        if(strpos($style,'U')!==false)
        {
            $this->underline = true;
            $style = str_replace('U','',$style);
        }
        else
            $this->underline = false;
        if($style=='IB')
            $style = 'BI';
        if($size==0)
            $size = $this->FontSizePt;
        // Test if font is already selected
        if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
            return;
        // Test if font is already loaded
        $fontkey = $family.$style;
        if(!isset($this->fonts[$fontkey]))
        {
            // Check if one of the core fonts
            if($family=='arial')
                $family = 'helvetica';
            if(in_array($family,$this->CoreFonts))
            {
                $this->_loadfont($fontkey);
            }
            else
                $this->Error('Undefined font: '.$family.' '.$style);
        }
        // Select it
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size/$this->k;
        if($this->page>0)
            $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->fonts[$fontkey]['i'],$this->FontSizePt));
    }

    function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        // Output a cell
        $k = $this->k;
        if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
        {
            // Automatic page break
            $x = $this->x;
            $ws = $this->ws;
            if($ws>0)
            {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);
            $this->x = $x;
            if($ws>0)
            {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw',$ws*$k));
            }
        }
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $s = '';
        if($fill || $border==1)
        {
            if($fill)
                $op = ($border==1) ? 'B' : 'f';
            else
                $op = 'S';
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
        }
        if(is_string($border))
        {
            $x = $this->x;
            $y = $this->y;
            if(strpos($border,'L')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
            if(strpos($border,'T')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
            if(strpos($border,'R')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
            if(strpos($border,'B')!==false)
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
        }
        if($txt!=='')
        {
            if(!isset($this->fonts[$this->FontFamily.$this->FontStyle]))
                $this->Error('Undefined font');
            if($align=='R')
                $dx = $w-$this->cMargin-$this->GetStringWidth($txt);
            elseif($align=='C')
                $dx = ($w-$this->GetStringWidth($txt))/2;
            else
                $dx = $this->cMargin;
            if($this->ColorFlag)
                $s .= 'q '.$this->TextColor.' ';
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$this->_escape($txt));
            if($this->underline)
                $s .= ' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
            if($this->ColorFlag)
                $s .= ' Q';
            if($link)
                $this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
        }
        if($s)
            $this->_out($s);
        $this->lasth = $h;
        if($ln>0)
        {
            // Go to next line
            $this->y += $h;
            if($ln==1)
                $this->x = $this->lMargin;
        }
        else
            $this->x += $w;
    }

    function GetStringWidth($s)
    {
        // Get width of a string in the current font
        $s = (string)$s;
        $cw = &$this->fonts[$this->FontFamily.$this->FontStyle]['cw'];
        $w = 0;
        $l = strlen($s);
        for($i=0;$i<$l;$i++)
            $w += $cw[$s[$i]];
        return $w*$this->FontSize/1000;
    }

    function Ln($h=null)
    {
        // Line feed; default value is the last cell height
        $this->x = $this->lMargin;
        if($h===null)
            $this->y += $this->lasth;
        else
            $this->y += $h;
    }

    function Output($dest='', $name='', $isUTF8=false)
    {
        // Output PDF to some destination
        if($this->state<3)
            $this->Close();
        if(strlen($name)==1 && strlen($dest)!=1)
        {
            // Fix parameter order
            $tmp = $dest;
            $dest = $name;
            $name = $tmp;
        }
        if($dest=='')
            $dest = 'I';
        if($name=='')
            $name = 'doc.pdf';
        switch(strtoupper($dest))
        {
            case 'I':
                // Send to standard output
                $this->_checkoutput();
                if(PHP_SAPI!='cli')
                {
                    // We send to a browser
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; '.$this->_httpencode('filename',$name,$isUTF8));
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->buffer;
                break;
            case 'D':
                // Download file
                $this->_checkoutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; '.$this->_httpencode('filename',$name,$isUTF8));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                // Save to local file
                if(!file_put_contents($name,$this->buffer))
                    $this->Error('Unable to create output file: '.$name);
                break;
            case 'S':
                // Return as a string
                return $this->buffer;
            default:
                $this->Error('Incorrect output destination: '.$dest);
        }
        return '';
    }

    // Protected methods
    protected function _dochecks()
    {
        // Check mbstring overloading
        if(ini_get('mbstring.func_overload') & 2)
            $this->Error('mbstring overloading must be disabled');
    }

    protected function _checkoutput()
    {
        if(PHP_SAPI!='cli')
        {
            if(headers_sent($file,$line))
                $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
        }
        if(ob_get_length())
        {
            // The output buffer is not empty
            if(preg_match('/^(\xEF\xBB\xBF)?\s*$/',ob_get_contents()))
            {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean it
                ob_clean();
            }
            else
                $this->Error("Some data has already been output, can't send PDF file");
        }
    }

    protected function _getpagesize($size)
    {
        if(is_string($size))
        {
            $size = strtolower($size);
            if(!isset($this->StdPageSizes[$size]))
                $this->Error('Unknown page size: '.$size);
            $a = $this->StdPageSizes[$size];
            return array($a[0]/$this->k, $a[1]/$this->k);
        }
        else
        {
            if($size[0]>$size[1])
                return array($size[1], $size[0]);
            else
                return $size;
        }
    }

    protected function _beginpage($orientation, $size, $rotation)
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
        // Check page size and orientation
        if($orientation=='')
            $orientation = $this->DefOrientation;
        else
        {
            $orientation = strtoupper($orientation[0]);
            if($orientation!=$this->DefOrientation)
                $this->OrientationChanges[$this->page] = true;
        }
        if($size=='')
            $size = $this->DefPageSize;
        else
            $size = $this->_getpagesize($size);
        if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
        {
            // New size or orientation
            if($orientation=='P')
            {
                $this->w = $size[0];
                $this->h = $size[1];
            }
            else
            {
                $this->w = $size[1];
                $this->h = $size[0];
            }
            $this->wPt = $this->w*$this->k;
            $this->hPt = $this->h*$this->k;
            $this->PageBreakTrigger = $this->h-$this->bMargin;
            $this->CurOrientation = $orientation;
            $this->CurPageSize = $size;
        }
        if($orientation!=$this->DefOrientation || $size[0]!=$this->DefPageSize[0] || $size[1]!=$this->DefPageSize[1])
            $this->PageInfo[$this->page]['size'] = array($this->wPt, $this->hPt);
        if($rotation!=0)
        {
            if($rotation%90!=0)
                $this->Error('Incorrect rotation value: '.$rotation);
            $this->CurRotation = $rotation;
            $this->PageInfo[$this->page]['rotation'] = $rotation;
        }
    }

    protected function _endpage()
    {
        $this->state = 1;
    }

    protected function _readint($f)
    {
        // Read a 4-byte integer from stream
        $a = unpack('Ni',$this->_readstream($f,4));
        return $a['i'];
    }

    function Error($msg)
    {
        // Fatal error
        throw new Exception('FPDF error: '.$msg);
    }
}
?>