<?php
namespace Mapbender\PrintBundle\Component;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use OwsProxy3\CoreBundle\Component\ProxyQuery;
use OwsProxy3\CoreBundle\Component\CommonProxy;

/**
 * Mapbender3 Print Service.
 *
 * @author Stefan Winkelmann
 */
class PrintService
{

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function doPrint($data)
    {
        $this->setup($data);
       
        if ($data['rotation'] == 0) {
            $this->createFinalMapImage();
        } else {
            $this->createFinalRotatedMapImage();
        }

        return $this->buildPdf();
    }

    private function setup($data)
    {
        // temp dir
        $this->tempDir = sys_get_temp_dir();
        // resource dir
        $this->resourceDir = $this->container->getParameter('kernel.root_dir') . '/Resources/MapbenderPrintBundle';
        
        // get user
        $securityContext = $this->container->get('security.context');
        $token = $securityContext->getToken();
        $this->user = $token->getUser();
        
        // data from client
        $this->data = $data;

        // template configuration from odg
        $odgParser = new OdgParser($this->container);
        $this->conf = $conf = $odgParser->getConf($data['template']);       
        
        // image size
        $this->imageWidth = round($conf['map']['width'] / 25.4 * $data['quality']);
        $this->imageHeight = round($conf['map']['height'] / 25.4 * $data['quality']);

        // map requests array
        $this->mapRequests = array();
        foreach ($data['layers'] as $i => $layer) {
            if ($layer['type'] === 'wms') {
                $url = strstr($data['layers'][$i]['url'], '&BBOX', true);
                $this->mapRequests[$i] = $url;
            } elseif ($layer['type'] === 'tms' || $layer['type'] === 'wmts') {
                if($data['quality'] === '288' ){
                    $this->mapRequests[$i] = $data['layers'][$i]['url'];
                }else{
                    $this->mapRequests[$i] = $data['layers'][$i];
                }
            } else {
                continue;
            }
        }

        if(isset($this->data['replace_pattern'])){
            $this->addReplacePattern();
        }

        $this->rotation = $rotation = $data['rotation'];
        $extentWidth = $data['extent']['width'];
        $extentHeight = $data['extent']['height'];
        $centerx = $data['center']['x'];
        $centery = $data['center']['y'];

        // switch if image is rotated
        if ($rotation == 0) {
            // calculate needed bbox
            $minX = $centerx - $extentWidth * 0.5;
            $minY = $centery - $extentHeight * 0.5;
            $maxX = $centerx + $extentWidth * 0.5;
            $maxY = $centery + $extentHeight * 0.5;

            $width = '&WIDTH=' . $this->imageWidth;
            $height =  '&HEIGHT=' . $this->imageHeight;
        }else{
            // calculate needed bbox 
            $neededExtentWidth = abs(sin(deg2rad($rotation)) * $extentHeight) +
                abs(cos(deg2rad($rotation)) * $extentWidth);
            $neededExtentHeight = abs(sin(deg2rad($rotation)) * $extentWidth) +
                abs(cos(deg2rad($rotation)) * $extentHeight);

            $this->neededExtentWidth = $neededExtentWidth;
            $this->neededExtentHeight = $neededExtentHeight;

            $minX = $centerx - $neededExtentWidth * 0.5;
            $minY = $centery - $neededExtentHeight * 0.5;
            $maxX = $centerx + $neededExtentWidth * 0.5;
            $maxY = $centery + $neededExtentHeight * 0.5;

            // calculate needed image size
            $neededImageWidth = round(abs(sin(deg2rad($rotation)) * $this->imageHeight) +
                abs(cos(deg2rad($rotation)) * $this->imageWidth));
            $neededImageHeight = round(abs(sin(deg2rad($rotation)) * $this->imageWidth) +
                abs(cos(deg2rad($rotation)) * $this->imageHeight));

            $this->neededImageWidth = $neededImageWidth;
            $this->neededImageHeight = $neededImageHeight;

            $width = '&WIDTH=' . $neededImageWidth;
            $height =  '&HEIGHT=' . $neededImageHeight;
        }

        foreach ($this->mapRequests as $i => &$request) {
            if (is_string($request)) { // only wms
                $request .= '&BBOX=' . $minX . ',' . $minY . ',' . $maxX . ',' . $maxY;
                $request .= $width . $height;
                if (!isset($this->data['replace_pattern'])) {
                    if ($this->data['quality'] != '72') {
                        $request .= '&map_resolution=' . $this->data['quality'];
                    }
                }
                $this->mapRequests[$i] = $request;
            } else { // wmts tms
                $request['bbox'] = array($minX, $minY, $maxX, $maxY);
                $request['neededHeight'] = isset($neededImageHeight) ? $neededImageHeight : $this->imageHeight;
                $request['neededWidth'] = isset($neededImageWidth) ? $neededImageWidth : $this->imageWidth;
                $request['width'] = $this->imageWidth;
                $request['height'] = $this->imageHeight;
            }
        }
    }

    private function addReplacePattern()
    {
        $quality = $this->data['quality'];
        $default = '';
        foreach ($this->mapRequests as $k => $url) {
            if (!is_string($url)){
                continue;
            }
            foreach ($this->data['replace_pattern'] as $rKey => $pattern) {
                if(isset($pattern['default'])){
                    if(isset($pattern['default'][$quality])){
                        $default = $pattern['default'][$quality];
                    }
                    continue;
                }
                if(strpos($url,$pattern['pattern']) === false){
                    continue;
                }
                if(strpos($url,$pattern['pattern']) !== false){
                    if(isset($pattern['replacement'][$quality]) && 
                            isset($pattern['replacement']['completeUrl']) && $pattern['replacement']['completeUrl'] === true){
                        $url = $pattern['replacement'][$quality];
                        $signer = $this->container->get('signer');
                        $this->mapRequests[$k] = $signer->signUrl($url);
                        continue 2;
                    } elseif (isset($pattern['replacement'][$quality])){
                        $url = str_replace($pattern['pattern'], $pattern['replacement'][$quality], $url);
                        $signer = $this->container->get('signer');
                        $this->mapRequests[$k] = $signer->signUrl($url);
                        continue 2;
                    }
                }

            }
            $url .= $default;
            $this->mapRequests[$k] = $url;
        }
    }

    private function createFinalMapImage()
    {
        $width = $this->imageWidth;
        $height = $this->imageHeight;
        $imageNames = $this->getImages($width, $height);

        // create final merged image
        $this->finalImageName = tempnam($this->tempDir, 'mb_print_final');
        $finalImage = imagecreatetruecolor($width, $height);
        $bg = ImageColorAllocate($finalImage, 255, 255, 255);
        imagefilledrectangle($finalImage, 0, 0, $width, $height, $bg);

        foreach ($imageNames as $imageName) {
            // Note: suppressing the errors IS bad, bad PHP wants us to do it that way
            $src = imagecreatefrompng($imageName);
            // Check that imagecreatefrompng did yield something
            if ($src) {
                $dest = $finalImage;
                imagecopy($dest, $src, 0, 0, 0, 0, $width, $height);
                imagepng($dest, $this->finalImageName);
                unlink($imageName);
            }
        }
        //draw features
        $this->drawFeatures();
    }

    private function createFinalRotatedMapImage()
    {
        $rotation = $this->rotation;
        $neededImageWidth = $this->neededImageWidth;
        $neededImageHeight = $this->neededImageHeight;
        $imageWidth = $this->imageWidth;
        $imageHeight = $this->imageHeight;

        $imageNames = $this->getImages($neededImageWidth,$neededImageHeight);

        // create temp merged image
        $tempImageName = tempnam($this->tempDir, 'mb_print_temp');
        $tempImage = imagecreatetruecolor($neededImageWidth, $neededImageHeight);
        $bg = ImageColorAllocate($tempImage, 255, 255, 255);
        imagefilledrectangle($tempImage, 0, 0, $neededImageWidth, $neededImageHeight, $bg);
        imagepng($tempImage, $tempImageName);

        foreach ($imageNames as $imageName) {
            // Note: suppressing the errors IS bad, bad PHP wants us to do it that way
            $src = imagecreatefrompng($imageName);
            // Check that imagecreatefrompng did yield something
            if ($src) {
                $dest = imagecreatefrompng($tempImageName);
                imagecopy($dest, $src, 0, 0, 0, 0, $neededImageWidth,
                    $neededImageHeight);
                imagepng($dest, $tempImageName);
                unlink($imageName);
            }
        }

        // draw features
        $this->finalImageName = $tempImageName;
        $this->drawFeatures();

        // rotate temp image
        $tempImage2 = imagecreatefrompng($tempImageName);
        $transColor = imagecolorallocatealpha($tempImage2, 255, 255, 255, 127);
        $rotatedImage = imagerotate($tempImage2, $rotation, $transColor);
        imagealphablending($rotatedImage, false);
        imagesavealpha($rotatedImage, true);
        $rotatedImageName = tempnam($this->tempDir, 'mb_print_rotated');
        imagepng($rotatedImage, $rotatedImageName);
        unlink($tempImageName);
        unlink($rotatedImageName);

        // clip final image from rotated
        $rotatedWidth = round(abs(sin(deg2rad($rotation)) * $neededImageHeight) +
            abs(cos(deg2rad($rotation)) * $neededImageWidth));
        $rotatedHeight = round(abs(sin(deg2rad($rotation)) * $neededImageWidth) +
            abs(cos(deg2rad($rotation)) * $neededImageHeight));
        $newx = ($rotatedWidth - $imageWidth ) / 2;
        $newy = ($rotatedHeight - $imageHeight ) / 2;

        $clippedImage = imagecreatetruecolor($imageWidth, $imageHeight);
        imagealphablending($clippedImage, false);
        imagesavealpha($clippedImage, true);
        imagecopy($clippedImage, $rotatedImage, 0, 0, $newx, $newy, $imageWidth, $imageHeight);

        $this->finalImageName = tempnam($this->tempDir, 'mb_print_final');
        imagepng($clippedImage, $this->finalImageName);
    }

    private function getWmtsImage($request, $imageName, $width, $height)
    {
        $logger = $this->container->get("logger");
//        $msg = '';
        $metersPerUnit = 1; // only 1 m /
        $scaleDenominator = floatval($this->data['scale_select']);
        $tilematrixset = $request['matrixset'];
        $matrix = null;
        foreach ($tilematrixset['tilematrices'] as $tilematrix) {
            if (round($scaleDenominator, 2) === round($tilematrix['scaleDenominator'], 2)) {
                $matrix = $tilematrix;
                break;
            }
        }
        if (!$matrix) {
            return null;
        }
        $tileWidth = isset($matrix['tileWidth']) ? $matrix['tileWidth'] : $tilematrixset['tileSize'][0];
        $tileHeight = isset($matrix['tileHeight']) ? $matrix['tileHeight'] : $tilematrixset['tileSize'][1];
        $topLeftX = isset($matrix['topLeftCorner']) ? $matrix['topLeftCorner'][0] : $tilematrixset['origin'][0];
        $topLeftY = isset($matrix['topLeftCorner']) ? $matrix['topLeftCorner'][1] : $tilematrixset['origin'][1];
        $bbox = $request['bbox'];
        $pixelSpan  = $scaleDenominator * 0.00028 / $metersPerUnit;
        $tileSpanX = $tileWidth * $pixelSpan;
        $tileSpanY = $tileHeight * $pixelSpan;

        $matrixSizeX = $matrix['matrixSize'][0];
        $matrixSizeY = $matrix['matrixSize'][1];
        $matrixBbox = array(
            $topLeftX,
            $topLeftY - ($tileSpanY * $matrixSizeY),
            $topLeftX + ($tileSpanX * $matrixSizeX),
            $topLeftY
        );
        $urlHelp = str_replace('{TileMatrixSet}', $tilematrixset['identifier'], $request['url']);
        $urlHelp = str_replace('{TileMatrix}', $matrix['identifier'], $urlHelp);
        $image = imagecreatetruecolor($width, $height);
        for ($tytop = $bbox[3]; $tytop >= $bbox[1]- $tileSpanY; $tytop = $tytop - $tileSpanY) {
            $tybottom = $tytop - $tileSpanY;
            if ($tybottom <= $matrixBbox[3] && $tytop >= $matrixBbox[1]) { // ??? check
                for ($txleft = $bbox[0]; $txleft <= $bbox[2] + $tileSpanX; $txleft = $txleft + $tileSpanX) {
                    $txright = $txleft + $tileSpanX;
                    if ($txright >= $matrixBbox[0] && $txleft <= $matrixBbox[2]) {
                        $xnum = floor(($txleft - $matrixBbox[0]) / $tileSpanX);
                        $ynum = $matrixSizeY - floor(($tytop - $matrixBbox[1]) / $tileSpanY) - 1;
                        $url = str_replace('{TileCol}', $xnum, $urlHelp);
                        $url = str_replace('{TileRow}', $ynum, $url);
                        $logger->debug("WMTS: " . $xnum . "-" . $ynum . " " . $url);
//                        $msg .= $xnum . "\t" . $ynum . "\t" . $url .'\n\n';
                        $imageName = tempnam($this->tempDir, 'mb_print_wmts' . $xnum . "-" . $ynum);

                        $rawImage = $this->getImage2($url, $imageName);
                        if ($rawImage !== null) {
                            $tx = $xnum * $tileSpanX + $matrixBbox[0];
                            $ty = $matrixBbox[3] - $ynum * $tileSpanY;
                            $txpos = intval(round(($tx - $bbox[0]) / $pixelSpan));
                            $typos = intval(round(($bbox[3] - $ty) / $pixelSpan));
//                            $msg .= $txpos . "\t" . $typos . "\t" . $xnum . "\t" . $ynum .'\t' . $url . '\n\n';
                            $image = $this->drawImage($image, $rawImage, $txpos, $typos, 0, 0, $tileWidth, $tileHeight, $tileWidth, $tileHeight);
                            unlink($imageName);
                        }
                    }
                }
            }
        }
//        $logger->debug($msg);
        return $image;
    }



    private function getTmsImage($request, $imageName, $width, $height)
    {
        $logger = $this->container->get("logger");
//        $msg = '';
        $tilematrixset = $request['options']['tilematrixset'];
        $tileWidth = $tilematrixset['tileSize'][0];
        $tileHeight = $tilematrixset['tileSize'][1];
        $tileMatrixStartX = $tilematrixset['origin'][0];
        $tileMatrixStartY = $tilematrixset['origin'][1];
        $matrix = $tilematrixset['tilesets'][$request['zoom']];
        $unitsPerPixel = $matrix['units-per-pixel'];
        $bbox = $request['bbox'];
        $matrixBbox = $request['options']['bbox'][$tilematrixset['supportedCrs']];
        $xstep = $unitsPerPixel * $tileWidth;
        $ystep = $unitsPerPixel * $tileHeight;
        $image = imagecreatetruecolor($width, $height);

        $minx = $bbox[0] > $matrixBbox[0] ? $bbox[0] : $matrixBbox[0];
        $miny = $bbox[1] > $matrixBbox[1] ? $bbox[1] : $matrixBbox[1];
        $maxx = $bbox[2] < $matrixBbox[2] ? $bbox[2] : $matrixBbox[2];
        $maxy = $bbox[3] < $matrixBbox[3] ? $bbox[3] : $matrixBbox[3];
        if ($minx < $maxx && $miny < $maxy) {
            $ynum = floor(($miny - $tileMatrixStartY) / $ystep);
            $ty0 = $ynum * $ystep + $tileMatrixStartY;
            for ($tileBottom = $ty0; $tileBottom <= $maxy; $tileBottom += $ystep, $ynum++) {
                $xnum = floor(($minx - $tileMatrixStartX) / $xstep);
                $tx0 = $xnum * $xstep + $tileMatrixStartX;
                for ($tileLeft = $tx0; $tileLeft <= $maxx; $tileLeft += $xstep, $xnum++) {
                    $url = $matrix['href'] . "/"  . $xnum . "/" . $ynum . "." . $request['options']['format_ext'];
                    $logger->debug("TMS: " . $xnum . "-" . $ynum . " " . $url);
                    $imageName = tempnam($this->tempDir, 'mb_print_tms' . $xnum . "-" . $ynum);
                    $rawImage = $this->getImage2($url, $imageName);
                    if ($rawImage !== null) {
                        $txpos = intval(round(($tileLeft - $minx) / $unitsPerPixel));
                        $typos = intval(round(($miny - $tileBottom) / $unitsPerPixel)) + $height - $tileHeight;
//                        $msg .= $txpos . "\t" . $typos . "\t" . $xnum . "\t" . $ynum .'\t' . $url . '\n\n';
                        $image = $this->drawImage($image, $rawImage, $txpos, $typos, 0, 0, $tileWidth, $tileHeight, $tileWidth, $tileHeight);
                        unlink($imageName);
                    }
                }
            }
        }
//        $logger->debug($msg);
        return $image;
    }

    private function getImage($request, $imageName)
    {
        $logger = $this->container->get("logger");
        $attributes = array();
        $attributes['_controller'] = 'OwsProxy3CoreBundle:OwsProxy:entryPoint';
        $subRequest = new Request(array(
            'url' => $request
            ), array(), $attributes, array(), array(), array(), '');
        $response = $this->container->get('http_kernel')->handle($subRequest,
            HttpKernelInterface::SUB_REQUEST);

        file_put_contents($imageName, $response->getContent());
        $rawImage = null;
        switch (trim($response->headers->get('content-type'))) {
            case 'image/png':
                $rawImage = imagecreatefrompng($imageName);
                break;
            case 'image/jpeg':
                $rawImage = imagecreatefromjpeg($imageName);
                break;
            case 'image/gif':
                $rawImage = imagecreatefromgif($imageName);
                break;
            default:
                $logger->debug("Unknown mimetype " . trim($response->headers->get('content-type')));
                continue;
        }
        return $rawImage;
    }
    
    private function getImage2($request, $imageName)
    {
        $logger = $this->container->get("logger");
        $path = array(
            '_controller' => 'OwsProxy3CoreBundle:OwsProxy:genericProxy',
            'url' => $request
        );
        $subRequest = $this->container->get('request')->duplicate(array(), null, $path);
        $response = $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

        file_put_contents($imageName, $response->getContent());
        $rawImage = null;
        switch (trim($response->headers->get('content-type'))) {
            case 'image/png':
                $rawImage = imagecreatefrompng($imageName);
                break;
            case 'image/jpeg':
                $rawImage = imagecreatefromjpeg($imageName);
                break;
            case 'image/gif':
                $rawImage = imagecreatefromgif($imageName);
                break;
            default:
                $logger->debug("Unknown mimetype " . trim($response->headers->get('content-type')));
                continue;
        }
        return $rawImage;
    }

    private function drawImage(&$dst_image, $src_image, $dst_x , $dst_y , $src_x , $src_y , $dst_w , $dst_h , $src_w , $src_h)
    {
        imagealphablending($dst_image, false);
        imagesavealpha($dst_image, true);
        imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
        return $dst_image;
    }

    private function setOpacity(&$image, $opacity)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $colorIn = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $alphaOut = 127 - (127 - $colorIn['alpha']) * $opacity;

                $colorOut = imagecolorallocatealpha(
                    $image,
                    $colorIn['red'],
                    $colorIn['green'],
                    $colorIn['blue'],
                    $alphaOut
                );
                imagesetpixel($image, $x, $y, $colorOut);
                imagecolordeallocate($image, $colorOut);
            }
        }
    }

    private function getImages($width, $height)
    {
        $logger = $this->container->get("logger");
        $imageNames = array();
        $rawImage = null;
        foreach ($this->mapRequests as $i => $request) {
            $imageName = tempnam($this->tempDir, 'mb_print');
            if (is_string($request)) { // wms
                $logger->debug("Print Request Nr.: " . $i . ' ' . $request);
                $rawImage = $this->getImage($request, $imageName);
            } elseif (is_array($request) && $request['type'] === 'tms') { // only tms
                $logger->debug("Print Request Nr.: " . $i . ' ' . $request['url']);
                $rawImage = $this->getTmsImage($request, $imageName, $width, $height);
            } elseif (is_array($request) && $request['type'] === 'wmts') { // only wmts
                $logger->debug("Print Request Nr.: " . $i . ' ' . $request['url']);
                $rawImage = $this->getWmtsImage($request, $imageName, $width, $height);
            } else { // tms, wmts
                continue;
            }
            if ($rawImage !== null) {
                $imageNames[] = $imageName;
                // Make sure input image is truecolor with alpha, regardless of input mode!
                $image = imagecreatetruecolor($width, $height);
                $this->drawImage($image, $rawImage, 0, 0, 0, 0, $width, $height, $width, $height);
                // Taking the painful way to alpha blending. Stupid PHP-GD
                $opacity = floatVal($this->data['layers'][$i]['opacity']);
                if (1.0 !== $opacity) {
                    $logger->debug("Print with opacity of: " . $opacity);
                    $width = imagesx($image);
                    $height = imagesy($image);
                    for ($x = 0; $x < $width; $x++) {
                        for ($y = 0; $y < $height; $y++) {
                            $colorIn = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                            $alphaOut = 127 - (127 - $colorIn['alpha']) * $opacity;

                            $colorOut = imagecolorallocatealpha(
                                $image,
                                $colorIn['red'],
                                $colorIn['green'],
                                $colorIn['blue'],
                                $alphaOut
                            );
                            imagesetpixel($image, $x, $y, $colorOut);
                            imagecolordeallocate($image, $colorOut);
                        }
                    }
                } else {
                    $logger->debug("Print without opacity");
                }
                imagepng($image, $imageName);
            }
        }
        return $imageNames;
    }

    private function buildPdf()
    {
        require_once('PDF_ImageAlpha.php');

        // set format
        if($this->conf['orientation'] == 'portrait'){
            $format = array($this->conf['pageSize']['width'],$this->conf['pageSize']['height']);
        }else{
            $format = array($this->conf['pageSize']['height'],$this->conf['pageSize']['width']);
        }

        $this->pdf = $pdf = new PDF_ImageAlpha($this->conf['orientation'], 'mm', $format);

        $template = $this->data['template'];
        $pdfFile = $this->resourceDir . '/templates/' . $template . '.pdf';
        $pageCount = $pdf->setSourceFile($pdfFile);
        $tplidx = $pdf->importPage(1);
        $pdf->SetAutoPageBreak(false);
        $pdf->addPage();
        $pdf->useTemplate($tplidx);

        // add final map image
        $mapUlX = $this->conf['map']['x'];
        $mapUlY = $this->conf['map']['y'];
        $mapWidth = $this->conf['map']['width'];
        $mapHeight = $this->conf['map']['height'];

        $pdf->Image($this->finalImageName, $mapUlX, $mapUlY,
                $mapWidth, $mapHeight, 'png', '', false, 0, 5, -1 * 0);
        // add map border (default is black)
        $pdf->Rect($mapUlX, $mapUlY, $mapWidth, $mapHeight);
        unlink($this->finalImageName);

        // add northarrow
        if (isset($this->conf['northarrow'])) {
            $this->addNorthArrow();
        }

        // fill text fields
        if (isset($this->conf['fields']) ) {
            foreach ($this->conf['fields'] as $k => $v) {
                $pdf->SetFont('Arial', '', $this->conf['fields'][$k]['fontsize']);
                $pdf->SetXY($this->conf['fields'][$k]['x'] - 1,
                    $this->conf['fields'][$k]['y']);

                // continue if extent field is set
                if(preg_match("/^extent/", $k)){
                    continue;
                }

                switch ($k) {
                    case 'date' :
                        $date = new \DateTime;
                        $pdf->Cell($this->conf['fields']['date']['width'],
                            $this->conf['fields']['date']['height'],
                            $date->format('d.m.Y'));
                        break;
                    case 'scale' :
                        $pdf->Cell($this->conf['fields']['scale']['width'],
                            $this->conf['fields']['scale']['height'],
                            '1 : ' . $this->data['scale_select']);
                        break;
                    default:
                        if (isset($this->data['extra'][$k])) {
                            $pdf->MultiCell($this->conf['fields'][$k]['width'],
                                $this->conf['fields'][$k]['height'],
                                $this->data['extra'][$k]);
                        }
                        break;
                }
            }
        }

        // add overview map
        if (isset($this->data['overview']) && isset($this->conf['overview']) ) {
            $this->addOverviewMap();
        }

        // add scalebar
        if (isset($this->conf['scalebar']) ) {
            $this->addScaleBar();
        }
        
        // add coordinates
        if (isset($this->conf['fields']['extent_ur_x']) && isset($this->conf['fields']['extent_ur_y']) 
                && isset($this->conf['fields']['extent_ll_x']) && isset($this->conf['fields']['extent_ll_y']))
        {
            $this->addCoordinates();
        }
        
        // add dynamic logo
        if ($this->conf['dynamic_image']){
            $this->addDynamicImage();
        }
        
        // add dynamic text
        if ($this->conf['fields']['dynamic_text']){
            $this->addDynamicText();
        }
        
        // add legend
        if (isset($this->data['legends']) && !empty($this->data['legends'])){
            $this->addLegend();
        }
            
        return $pdf->Output(null, 'S');
    }

    private function addNorthArrow()
    {
        $rotation = $this->rotation;
        $northarrow = $this->resourceDir . '/images/northarrow.png';

        if($rotation != 0){
            $image = imagecreatefrompng($northarrow);
            $transColor = imagecolorallocatealpha($image, 255, 255, 255, 0);
            $rotatedImage = imagerotate($image, $rotation, $transColor);
            $rotatedImageName = tempnam($this->tempdir, 'mb_northarrow');
            imagepng($rotatedImage, $rotatedImageName);

            if ($rotation == 90 || $rotation == 270) {
                //
            } else {
                $srcImage = imagecreatefrompng($rotatedImageName);
                $srcSize = getimagesize($rotatedImageName);
                $destSize = getimagesize($northarrow);
                $x = ($srcSize[0] - $destSize[0]) / 2;
                $y = ($srcSize[1] - $destSize[1]) / 2;
                $destImage = imagecreatetruecolor($destSize[0], $destSize[1]);
                imagecopy($destImage, $srcImage, 0, 0, $x, $y, $srcSize[0], $srcSize[1]);
                imagepng($destImage, $rotatedImageName);
            }
            $northarrow = $rotatedImageName;
        }

        $this->pdf->Image($northarrow,
                            $this->conf['northarrow']['x'],
                            $this->conf['northarrow']['y'],
                            $this->conf['northarrow']['width'],
                            $this->conf['northarrow']['height'],
                            'png');
        if($rotation != 0){
            unlink($rotatedImageName);
        }
    }

    private function addOverviewMap()
    {
        // calculate needed bbox
        $ovWidth = $this->conf['overview']['width'] * $this->data['overview'][0]['scale'] / 1000;
        $ovHeight = $this->conf['overview']['height'] * $this->data['overview'][0]['scale'] / 1000;
        $centerx = $this->data['center']['x'];
        $centery = $this->data['center']['y'];
        $minX = $centerx - $ovWidth * 0.5;
        $minY = $centery - $ovHeight * 0.5;
        $maxX = $centerx + $ovWidth * 0.5;
        $maxY = $centery + $ovHeight * 0.5;
        $bbox = '&BBOX=' . $minX . ',' . $minY . ',' . $maxX . ',' . $maxY;

        // calculate needed image size
        $quality = $this->data['quality'];
        $ovImageWidth = round($this->conf['overview']['width'] / 25.4 * $quality);
        $ovImageHeight = round($this->conf['overview']['height'] / 25.4 * $quality);
        $width = '&WIDTH=' . $ovImageWidth;
        $height = '&HEIGHT=' . $ovImageHeight;

        // get images
        $tempNames = array();
        $logger = $this->container->get("logger");
        foreach ($this->data['overview'] as $i => $layer) {
            $url = strstr($this->data['overview'][$i]['url'], '&BBOX', true);
            $url .= $bbox . $width . $height;

            $logger->debug("Print Overview Request Nr.: " . $i . ' ' . $url);
            $attributes = array();
            $attributes['_controller'] = 'OwsProxy3CoreBundle:OwsProxy:entryPoint';
            $subRequest = new Request(array(
                'url' => $url
                ), array(), $attributes, array(), array(), array(), '');
            $response = $this->container->get('http_kernel')->handle($subRequest,
                HttpKernelInterface::SUB_REQUEST);

            $imageName = tempnam($this->tempdir, 'mb_print');
            $tempNames[] = $imageName;

            file_put_contents($imageName, $response->getContent());
            $im = null;
            switch (trim($response->headers->get('content-type'))) {
                case 'image/png' :
                    $im = imagecreatefrompng($imageName);
                    break;
                case 'image/jpeg' :
                    $im = imagecreatefromjpeg($imageName);
                    break;
                case 'image/gif' :
                    $im = imagecreatefromgif($imageName);
                    break;
                default:
                    $logger->debug("Unknown mimetype " . trim($response->headers->get('content-type')));
                    continue;
            }
            if ($im !== null) {
                imagesavealpha($im, true);
                imagepng($im, $imageName);
            }
        }

        // create final merged image
        $finalImageName = tempnam($this->tempdir, 'mb_print_merged');
        $finalImage = imagecreatetruecolor($ovImageWidth, $ovImageHeight);
        $bg = ImageColorAllocate($finalImage, 255, 255, 255);
        imagefilledrectangle($finalImage, 0, 0, $ovImageWidth,
            $ovImageHeight, $bg);
        imagepng($finalImage, $finalImageName);
        foreach ($tempNames as $tempName) {
            // Note: suppressing the errors IS bad, bad PHP wants us to do it that way
            $src = imagecreatefrompng($tempName);
            // Check that imagecreatefrompng did yield something
            if ($src) {
                $dest = imagecreatefrompng($finalImageName);
                $src = imagecreatefrompng($tempName);
                imagecopy($dest, $src, 0, 0, 0, 0, $ovImageWidth,
                    $ovImageHeight);
                imagepng($dest, $finalImageName);
            }
            unlink($tempName);
        }

        $image = imagecreatefrompng($finalImageName);

        // add red extent rectangle
        $ll_x = $this->data['extent_feature'][3]['x'];
        $ll_y = $this->data['extent_feature'][3]['y'];
        $ul_x = $this->data['extent_feature'][0]['x'];
        $ul_y = $this->data['extent_feature'][0]['y'];

        $lr_x = $this->data['extent_feature'][2]['x'];
        $lr_y = $this->data['extent_feature'][2]['y'];
        $ur_x = $this->data['extent_feature'][1]['x'];
        $ur_y = $this->data['extent_feature'][1]['y'];

        $p1 = $this->realWorld2ovMapPos($ovWidth, $ovHeight, $ll_x, $ll_y);
        $p2 = $this->realWorld2ovMapPos($ovWidth, $ovHeight, $ul_x, $ul_y);
        $p3 = $this->realWorld2ovMapPos($ovWidth, $ovHeight, $ur_x, $ur_y);
        $p4 = $this->realWorld2ovMapPos($ovWidth, $ovHeight, $lr_x, $lr_y);

        $red = ImageColorAllocate($image,255,0,0);
        imageline ( $image, $p1[0], $p1[1], $p2[0], $p2[1], $red);
        imageline ( $image, $p2[0], $p2[1], $p3[0], $p3[1], $red);
        imageline ( $image, $p3[0], $p3[1], $p4[0], $p4[1], $red);
        imageline ( $image, $p4[0], $p4[1], $p1[0], $p1[1], $red);

        imagepng($image, $finalImageName);

        // add image to pdf
        $this->pdf->Image($finalImageName,
                    $this->conf['overview']['x'],
                    $this->conf['overview']['y'],
                    $this->conf['overview']['width'],
                    $this->conf['overview']['height'],
                    'png');
        // draw border rectangle
        $this->pdf->Rect($this->conf['overview']['x'],
                         $this->conf['overview']['y'],
                         $this->conf['overview']['width'],
                         $this->conf['overview']['height']);

        unlink($finalImageName);
    }

    private function addScaleBar()
    {
        $pdf = $this->pdf;
        $pdf->SetLineWidth(0.1);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(0,0,0);
        $pdf->SetFont('arial', '', 10 );

        $length = 0.01 * $this->data['scale_select'] * 5;
        $suffix = 'm';

        $pdf->Text( $this->conf['scalebar']['x'] -1 , $this->conf['scalebar']['y'] - 1 , '0' );
        $pdf->Text( $this->conf['scalebar']['x'] + 46, $this->conf['scalebar']['y'] - 1 , $length . '' . $suffix);

        $pdf->Rect($this->conf['scalebar']['x'], $this->conf['scalebar']['y'], 10, 2, 'FD');
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($this->conf['scalebar']['x'] + 10 , $this->conf['scalebar']['y'], 10, 2, 'FD');
        $pdf->SetFillColor(0,0,0);
        $pdf->Rect($this->conf['scalebar']['x'] + 20  , $this->conf['scalebar']['y'], 10, 2, 'FD');
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($this->conf['scalebar']['x'] + 30 , $this->conf['scalebar']['y'], 10, 2, 'FD');
        $pdf->SetFillColor(0,0,0);
        $pdf->Rect($this->conf['scalebar']['x'] + 40  , $this->conf['scalebar']['y'], 10, 2, 'FD');
    }
    
    private function addCoordinates()
    {
        $pdf = $this->pdf;
        
        $corrFactor = 2;
        $precision = 2;
        // correction factor and round precision if WGS84
        if($this->data['extent']['width'] < 1){
             $corrFactor = 3;
             $precision = 6;
        }
        
        // upper right Y
        $pdf->SetFont('Arial', '', $this->conf['fields']['extent_ur_y']['fontsize']);          
        $pdf->Text($this->conf['fields']['extent_ur_y']['x'] + $corrFactor,
                    $this->conf['fields']['extent_ur_y']['y'] + 3,
                    str_replace('.', ',', round($this->data['extent_feature'][2]['y'], $precision)));

        // upper right X
        $pdf->SetFont('Arial', '', $this->conf['fields']['extent_ur_x']['fontsize']);       
        $pdf->RotatedText($this->conf['fields']['extent_ur_x']['x'] + 1,
                    $this->conf['fields']['extent_ur_x']['y'],
                    str_replace('.', ',', round($this->data['extent_feature'][2]['x'], $precision)),-90);

        // lower left Y
        $pdf->SetFont('Arial', '', $this->conf['fields']['extent_ll_y']['fontsize']);          
        $pdf->Text($this->conf['fields']['extent_ll_y']['x'],
                    $this->conf['fields']['extent_ll_y']['y'] + 3,
                    str_replace('.', ',', round($this->data['extent_feature'][0]['y'], $precision)));

        // lower left X
        $pdf->SetFont('Arial', '', $this->conf['fields']['extent_ll_x']['fontsize']);
        $pdf->RotatedText($this->conf['fields']['extent_ll_x']['x'] + 3,
                    $this->conf['fields']['extent_ll_x']['y'] + 30,
                    str_replace('.', ',', round($this->data['extent_feature'][0]['x'], $precision)),90);
    }
    
    private function addDynamicImage()
    {
        if($this->user == 'anon.'){
            return;
        }

        $groups = $this->user->getGroups();
        $group = $groups[0];
        
        if(!isset($group)){
            return;
        }
        
        $dynImage = $this->resourceDir . '/images/' . $group->getTitle() . '.png';
        if(file_exists ($dynImage)){
            $this->pdf->Image($dynImage,
                            $this->conf['dynamic_image']['x'],
                            $this->conf['dynamic_image']['y'],
                            0,
                            $this->conf['dynamic_image']['height'],
                            'png');
            return;
        }

    }
    
    private function addDynamicText()
    {
        if($this->user == 'anon.'){
            return;
        }
        
        $groups = $this->user->getGroups();
        $group = $groups[0];
        
        if(!isset($group)){
            return;
        }
        
        $this->pdf->SetFont('Arial', '', $this->conf['fields']['dynamic_text']['fontsize']);
        $this->pdf->MultiCell($this->conf['fields']['dynamic_text']['width'],
                $this->conf['fields']['dynamic_text']['height'],
                $group->getDescription());
        
    }    
    
    private function getColor($color, $alpha, $image)
    {
        list($r, $g, $b) = CSSColorParser::parse($color);

        if(0 == $alpha) {
            return ImageColorAllocate($image, $r, $g, $b);
        } else {
            $a = (1 - $alpha) * 127.0;
            return imagecolorallocatealpha($image, $r, $g, $b, $a);
        }
    }

    private function drawPolygon($geometry, $image)
    {
        foreach($geometry['coordinates'] as $ring) {
            if(count($ring) < 3) {
                continue;
            }

            $points = array();
            foreach($ring as $c) {
                if($this->rotation == 0){
                    $p = $this->realWorld2mapPos($c[0], $c[1]);
                }else{
                    $p = $this->realWorld2rotatedMapPos($c[0], $c[1]);
                }
                $points[] = floatval($p[0]);
                $points[] = floatval($p[1]);
            }
            imagesetthickness($image, 0);
            // Filled area
            if($geometry['style']['fillOpacity'] > 0){
                $color = $this->getColor(
                    $geometry['style']['fillColor'],
                    $geometry['style']['fillOpacity'],
                    $image);
                imagefilledpolygon($image, $points, count($ring), $color);
            }
            // Border
            $color = $this->getColor(
                $geometry['style']['strokeColor'],
                $geometry['style']['strokeOpacity'],
                $image);
            imagesetthickness($image, $geometry['style']['strokeWidth']);
            imagepolygon($image, $points, count($ring), $color);
        }
    }

    private function drawMultiPolygon($geometry, $image)
    {
        foreach($geometry['coordinates'][0] as $ring) {
            if(count($ring) < 3) {
                continue;
            }

            $points = array();
            foreach($ring as $c) {
                if($this->rotation == 0){
                    $p = $this->realWorld2mapPos($c[0], $c[1]);
                }else{
                    $p = $this->realWorld2rotatedMapPos($c[0], $c[1]);
                }
                $points[] = floatval($p[0]);
                $points[] = floatval($p[1]);
            }
            imagesetthickness($image, 0);
            // Filled area
            if($geometry['style']['fillOpacity'] > 0){
                $color = $this->getColor(
                    $geometry['style']['fillColor'],
                    $geometry['style']['fillOpacity'],
                    $image);
                imagefilledpolygon($image, $points, count($ring), $color);
            }
            // Border
            $color = $this->getColor(
                $geometry['style']['strokeColor'],
                $geometry['style']['strokeOpacity'],
                $image);
            imagesetthickness($image, $geometry['style']['strokeWidth']);
            imagepolygon($image, $points, count($ring), $color);
        }
    }

    private function drawLineString($geometry, $image)
    {
        $color = $this->getColor(
            $geometry['style']['strokeColor'],
            $geometry['style']['strokeOpacity'],
            $image);
        imagesetthickness($image, $geometry['style']['strokeWidth']);

        for($i = 1; $i < count($geometry['coordinates']); $i++) {

            if($this->rotation == 0){
                $from = $this->realWorld2mapPos(
                    $geometry['coordinates'][$i - 1][0],
                    $geometry['coordinates'][$i - 1][1]);
                $to = $this->realWorld2mapPos(
                    $geometry['coordinates'][$i][0],
                    $geometry['coordinates'][$i][1]);
            }else{
                $from = $this->realWorld2rotatedMapPos(
                    $geometry['coordinates'][$i - 1][0],
                    $geometry['coordinates'][$i - 1][1]);
                $to = $this->realWorld2rotatedMapPos(
                    $geometry['coordinates'][$i][0],
                    $geometry['coordinates'][$i][1]);
            }

            imageline($image, $from[0], $from[1], $to[0], $to[1], $color);
        }
    }
	
    private function drawMultiLineString($geometry, $image)
    {
        $color = $this->getColor(
            $geometry['style']['strokeColor'],
            $geometry['style']['strokeOpacity'],
            $image);
        imagesetthickness($image, $geometry['style']['strokeWidth']);
	
		foreach($geometry['coordinates'] as $coords) {
		
			for($i = 1; $i < count($coords); $i++) {

				if($this->rotation == 0){
					$from = $this->realWorld2mapPos(
						$coords[$i - 1][0],
						$coords[$i - 1][1]);
					$to = $this->realWorld2mapPos(
						$coords[$i][0],
						$coords[$i][1]);
				}else{
					$from = $this->realWorld2rotatedMapPos(
						$coords[$i - 1][0],
						$coords[$i - 1][1]);
					$to = $this->realWorld2rotatedMapPos(
						$coords[$i][0],
						$coords[$i][1]);
				}

				imageline($image, $from[0], $from[1], $to[0], $to[1], $color);
			}
		}
    }

    private function drawPoint($geometry, $image)
    {
        $c = $geometry['coordinates'];

        if($this->rotation == 0){
            $p = $this->realWorld2mapPos($c[0], $c[1]);
        }else{
            $p = $this->realWorld2rotatedMapPos($c[0], $c[1]);
        }

        if(isset($geometry['style']['label'])){
            // draw label with white halo
            $bgcolor = $this->getColor('#ffffff', 1, $image);
            $fontPath = $this->resourceDir.'/fonts/';
            $font = $fontPath . 'OpenSans-Bold.ttf';
            if(isset($geometry['style']['display']) && $geometry['style']['display'] == 'popup') {
                $fontSize = 30;
                $color = $this->getColor('#780046', 1, $image);
                $p[0] = $p[0] + 25;
                $p[1] = $p[1] - 25;
            } else {
                $color = $this->getColor('#ff0000', 1, $image);
                $fontSize = 22;
            }
            imagettftext($image, $fontSize, 0, $p[0], $p[1]+1, $bgcolor, $font, $geometry['style']['label']);
            imagettftext($image, $fontSize, 0, $p[0], $p[1]-1, $bgcolor, $font, $geometry['style']['label']);
            imagettftext($image, $fontSize, 0, $p[0]-1, $p[1], $bgcolor, $font, $geometry['style']['label']);
            imagettftext($image, $fontSize, 0, $p[0]+1, $p[1], $bgcolor, $font, $geometry['style']['label']);
            imagettftext($image, $fontSize, 0, $p[0], $p[1], $color, $font, $geometry['style']['label']);
            return;
        }

        $radius = $geometry['style']['pointRadius'];
        // Filled circle
        if($geometry['style']['fillOpacity'] > 0){
            $color = $this->getColor(
                $geometry['style']['fillColor'],
                $geometry['style']['fillOpacity'],
                $image);
            imagefilledellipse($image, $p[0], $p[1], 2*$radius, 2*$radius, $color);
        }
        // Circle border
        $color = $this->getColor(
            $geometry['style']['strokeColor'],
            $geometry['style']['strokeOpacity'],
            $image);
        imageellipse($image, $p[0], $p[1], 2*$radius, 2*$radius, $color);
    }

    private function drawFeatures()
    {
        $image = imagecreatefrompng($this->finalImageName);
        imagesavealpha($image, true);
        imagealphablending($image, true);

        foreach($this->data['layers'] as $idx => $layer) {
            if('GeoJSON+Style' !== $layer['type']) {
                continue;
            }

            foreach($layer['geometries'] as $geometry) {
                $renderMethodName = 'draw' . $geometry['type'];
                if(!method_exists($this, $renderMethodName)) {
                    continue;
                    //throw new \RuntimeException('Can not draw geometries of type "' . $geometry['type'] . '".');
                }
                $this->$renderMethodName($geometry, $image);
            }
        }
        imagepng($image, $this->finalImageName);
    }

    private function addLegend()
    {
        $this->pdf->addPage('P');
        $this->pdf->SetFont('Arial', 'B', 7);
        $x = 10;
        $y = 10;

        foreach ($this->data['legends'] as $idx => $legendArray) {
            $c = 1;
            $arraySize = count($legendArray);
            foreach ($legendArray as $title => $legendUrl) {

                if (preg_match('/request=GetLegendGraphic/i', $legendUrl) === 0) {
                    continue;
                }

                $image = $this->getLegendImage($legendUrl);
                if (false === @imagecreatefromstring(@file_get_contents($image))) {
                    continue;
                }
                $size = getimagesize($image);
                $tempY = round($size[1] * 25.4 / 144) + 10;

                if($c> 1){
                    if($y + $tempY > ($this->pdf->h)){
                        $x += 100;
                        $y = 10;
                        if($x > ($this->pdf->w)){
                            $this->pdf->addPage('P');
                            $x = 10;
                            $y = 10;
                        }
                    }
                }

                $this->pdf->setXY($x,$y);
                $this->pdf->Cell(0,0,  utf8_decode($title));
                //$this->pdf->Image($image, $x, $y + 5, 0, 0, 'png', '', false, 0);
                $this->pdf->Image($image, $x, $y + 5, ($size[0] * 25.4 / 144), ($size[1] * 25.4 / 144), 'png', '', false, 0);

                $y += round($size[1] * 25.4 / 144) + 10;
                if($y > ($this->pdf->h)){
                    $x += 105;
                    $y = 10;
                }
                if($x > ($this->pdf->w) && $c < $arraySize){
                    $this->pdf->addPage('P');
                    $x = 10;
                    $y = 10;
                }
                unlink($image);
                $c++;
            }
        }
    }

    private function getLegendImage($unsignedUrl)
    {
        $unsignedUrl = urldecode($unsignedUrl);
        $signer = $this->container->get('signer');
        $url = $signer->signUrl($unsignedUrl);

        $proxy_config = $this->container->getParameter("owsproxy.proxy");
        $proxy_query = ProxyQuery::createFromUrl($url);
        $proxy = new CommonProxy($proxy_config, $proxy_query);
        $browserResponse = $proxy->handle();

        $imagename = tempnam($this->tempdir, 'mb_printlegend');
        file_put_contents($imagename, $browserResponse->getContent());

        return $imagename;
    }

    private function realWorld2mapPos($rw_x,$rw_y)
    {
        $quality = $this->data['quality'];
        $mapWidth = $this->data['extent']['width'];
        $mapHeight = $this->data['extent']['height'];
        $centerx = $this->data['center']['x'];
        $centery = $this->data['center']['y'];
        $minX = $centerx - $mapWidth * 0.5;
        $minY = $centery - $mapHeight * 0.5;
        $maxX = $centerx + $mapWidth * 0.5;
        $maxY = $centery + $mapHeight * 0.5;
        $extentx = $maxX - $minX ;
	$extenty = $maxY - $minY ;
        $pixPos_x = (($rw_x - $minX)/$extentx) * round($this->conf['map']['width']  / 25.4 * $quality) ;
	$pixPos_y = (($maxY - $rw_y)/$extenty) * round($this->conf['map']['height']  / 25.4 * $quality);

	return array($pixPos_x, $pixPos_y);
    }

    private function realWorld2ovMapPos($ovWidth, $ovHeight, $rw_x,$rw_y)
    {
        $quality = $this->data['quality'];
        $centerx = $this->data['center']['x'];
        $centery = $this->data['center']['y'];
        $minX = $centerx - $ovWidth * 0.5;
        $minY = $centery - $ovHeight * 0.5;
        $maxX = $centerx + $ovWidth * 0.5;
        $maxY = $centery + $ovHeight * 0.5;
        $extentx = $maxX - $minX ;
	$extenty = $maxY - $minY ;
        $pixPos_x = (($rw_x - $minX)/$extentx) * round($this->conf['overview']['width'] / 25.4 * $quality) ;
	$pixPos_y = (($maxY - $rw_y)/$extenty) * round($this->conf['overview']['height'] / 25.4 * $quality);

	return array($pixPos_x, $pixPos_y);
    }

    private function realWorld2rotatedMapPos($rw_x,$rw_y)
    {
        $centerx = $this->data['center']['x'];
        $centery = $this->data['center']['y'];
        $minX = $centerx - $this->neededExtentWidth * 0.5;
        $minY = $centery - $this->neededExtentHeight * 0.5;
        $maxX = $centerx + $this->neededExtentWidth * 0.5;
        $maxY = $centery + $this->neededExtentHeight * 0.5;
        $extentx = $maxX - $minX ;
	$extenty = $maxY - $minY ;
        $pixPos_x = (($rw_x - $minX)/$extentx) * $this->neededImageWidth;
	$pixPos_y = (($maxY - $rw_y)/$extenty) * $this->neededImageHeight;

	return array($pixPos_x, $pixPos_y);
    }

}