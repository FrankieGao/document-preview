<?php

$dir = dirname(__FILE__)."/";

const LIB_DIR   = 'lib/';
const FILE_DIR  = 'files/';
const THUMB_DIR = 'files/thumbs/';

const THUMB_W = 300;
const THUMB_H = 400;


if(isset($_FILES) && !empty($_FILES)){
    
    $fileMimeType   = $_FILES['document']['type'];
    $fileName       = $_FILES['document']['name'];
    $tmpFile        = $_FILES['document']['tmp_name'];

    $sourceFile     = $dir.FILE_DIR.$fileName;
    $pdfFileName    = $dir.FILE_DIR.substr($fileName,0, strrpos($fileName, ".")).".pdf";
    $jpgFileName    = $dir.THUMB_DIR.substr($fileName,0, strrpos($fileName, ".")).".jpg";
    
    $useUnoconv = false;

    $moved = move_uploaded_file($tmpFile, $sourceFile);
    if( $moved !== false){
        chmod($sourceFile, 0777);
    }    
    
    switch($fileMimeType){
        
        case 'application/pdf':
            # do nothing, file is already a pdf
            break;

        case 'application/msword':
        case 'application/vnd.ms-excel':
        default:
            $useUnoconv = true;
            # nginx 
            #putenv('PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games:/opt/node/bin');
            $command = strtr('unoconv --format=pdf --output=%output% %file% 2>&1', array(
                '%output%'      => escapeshellarg($pdfFileName),
                '%file%'        => escapeshellarg($sourceFile),
            ));
            
            #die($command);
            $output = shell_exec($command);
            if(strpos($output, "Error") !== false){
                $error = $output;
            }
        break;
    }

    
    if(!isset($error)){
        # create thumb from PDF
        try
        {
            $imagick = new imagick();
            $imagick->readImage($pdfFileName."[0]");    
            $imagick->setImageFormat("jpg");
            $imagick->setimagebackgroundcolor('white');
            $imagick->setImageCompression(imagick::COMPRESSION_JPEG);

            $imagick2 = $imagick->flattenImages();
            $imagick2->thumbnailImage(THUMB_W, THUMB_H);
            $imagick2->stripimage();
            $imagick2->setImageCompressionQuality(75);
            $imagick2->writeImage($jpgFileName);

            chmod($jpgFileName, 0777);
            $imgUrl = str_replace($_SERVER['DOCUMENT_ROOT'], "", $jpgFileName);

            // removes the generated pdf file
            if($useUnoconv){
                unlink($pdfFileName);
            }
            #unlink($sourceFile);

        }catch(Exception $e){
            print_r($e);
            die;
        }          
    }

}

$showCommand = "unoconv --show 2>&1";
$show = shell_exec($showCommand);

?>
<html>
    <body>
        <h1>Document preview test</h1>
        
        <p>Folder : "<?php echo $dir.FILE_DIR ?>" <?php echo (is_writable($dir.FILE_DIR)) ? 'writable' : 'not writable' ?></p>
        <hr />
        
        <?php if(isset($error)) : ?>
        <div style="border:solid 1px #FF0000">
            unoconv output :<br />
            <?php echo $error ?>
        </div>
        <?php endif; ?>
        
        <form name="upload" method="post" enctype="multipart/form-data">
            <input type="file" name="document" />
            <br />
            <input type="submit">
        </form>
        
        <div>
            <?php if(isset($imgUrl)) : ?>
            <?php echo $sourceFile ?>
            <hr />
            
            <img src="<?php echo $imgUrl ?>" style="border:solid 1px;"/>
            <?php endif; ?>
        </div>

        <?php if(isset($command)) : ?>
        <div>
            <h3>unoconv command</h3>
            <pre><?php echo $command ?></pre>
            <strong>Output</strong>
            <pre><?php echo $output ?></pre>
        </div>
        <hr/>
        <?php endif; ?>

        
        <div>
            <h3>unoconv formats</h3>
            <code><?php echo $showCommand ?></code>
            <pre><?php echo $show ?></pre>
        </div>
    </body>
</html>