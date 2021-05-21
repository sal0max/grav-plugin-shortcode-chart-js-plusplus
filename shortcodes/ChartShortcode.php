<?php /** @noinspection PhpUnused */

namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class ChartShortcode extends Shortcode
{
    // id to connect javascript with canvas
    private $canvasId;
    // default colors
    private $backgroundColor;      // line fill / bar fill
    private $borderColor;          // line color / bar border
    private $pointBackgroundColor; // points fill
    private $pointBorderColor;     // points border

    /*
     *
     */
    public function init()
    {
        // chart
        $this->shortcode->getHandlers()->add('chart', function(ShortcodeInterface $sc) {
            // get plugin settings
            $pluginConfig               = $this->config->get('plugins.shortcode-chart-js-plusplus');
            $this->backgroundColor      = $pluginConfig['chart']['backgroundColor'];
            $this->borderColor          = $pluginConfig['chart']['borderColor'];
            $this->pointBackgroundColor = $pluginConfig['chart']['pointBackgroundColor'];
            $this->pointBorderColor     = $pluginConfig['chart']['pointBorderColor'];

            //
            $output  = $this->buildCanvas();
            $output .= $this->buildJS($sc);
            return $output;
        });

        // canvas
        $this->shortcode->getHandlers()->add('canvas', function(ShortcodeInterface $sc) {
            $hash = $this->shortcode->getId($sc->getParent());
            $this->shortcode->setStates("canvas-$hash", $sc);
            return '';
        });

        // dataset
        $this->shortcode->getHandlers()->add('dataset', function(ShortcodeInterface $sc) {
            $hash = $this->shortcode->getId($sc->getParent());
            $this->shortcode->setStates("dataset-$hash", $sc);
            return '';
        });

        // options
        $this->shortcode->getHandlers()->add('options', function(ShortcodeInterface $sc) {
            $hash = $this->shortcode->getId($sc->getParent());
            $this->shortcode->setStates("options-$hash", $sc);
            return '';
        });
    }


    /*
     *
     */
    private function buildCanvas() : string
    {
        $hash=base64_encode(openssl_random_pseudo_bytes(16));
        $canvas = $this->shortcode->getStates("canvas-$hash");
        // Canvas details
        if (!empty($canvas)) {
            $this->canvasId = end($canvas)->getParameter('id', "canvas-$hash");
            $width          = end($canvas)->getParameter('width');
            $height         = end($canvas)->getParameter('height');
            return "<canvas id=\"$this->canvasId\" width=\"$width\" height=\"$height\"></canvas>\n";
        } else {
            $this->canvasId = "canvas-$hash";
            return "<canvas id=\"$this->canvasId\"></canvas>\n";
        }
    }


    /*
     *
     */
    private function buildJS($sc) : string
    {
        $hash   = $this->shortcode->getId($sc);
        $output = "<script>";

        /*
         * add assets
         */
        $this->shortcode->addAssets('js',  'plugin://shortcode-chart-js-plusplus/vendor/chartjs/chart.min.js');

        /*
         * BEGINN JS BLOCK
         */
        $output .= "var ctx = document.getElementById('$this->canvasId');\n";
        $output .= "var aChart = new Chart(ctx, {\n";

        /*
         * type
         */
        $output .= "type: '{$sc->getParameter('type')}',\n";

        /*
         * data
         */
        $output .= "data: {\n";
        $output .= $this->shortcodeOptionsToJsValues($sc->getParameters());

        /*
         * datasets
         */
        $datasets = $this->shortcode->getStates("dataset-$hash");
        $output .= "datasets: [";
        if (!empty($datasets)) {
            foreach ($datasets as $dataset) {
                // go on
                $output .= "{\n";
                $output .= $this->shortcodeOptionsToJsValues($dataset->getParameters());
                // fill in default values, if needed
                if ($dataset->getParameter('backgroundColor') === null)
                    $output .= "backgroundColor: '$this->backgroundColor',\n";
                if ($dataset->getParameter('borderColor') === null)
                    $output .= "borderColor: '$this->borderColor',\n";
                if ($dataset->getParameter('pointBackgroundColor') === null)
                    $output .= "pointBackgroundColor: '$this->pointBackgroundColor',\n";
                if ($dataset->getParameter('pointBorderColor') === null)
                    $output .= "pointBorderColor: '$this->pointBorderColor',\n";
                $output .= "}, ";
            }
        }
        $output .= "]\n";
        $output .= "},\n";

        /*
         * options
         */
        $options = $this->shortcode->getStates("options-$hash");
        if (!empty($options)) {
            $output .= "options: {\n";
            $output .= $this->shortcodeOptionsToJsValues($options[0]->getParameters());
            $output .= "}\n";
        }

        $output .= '});';

        /*
         * END JS BLOCK
         */
        $output .= "</script>";

        return $output;
    }


    /*
     *
     */
    private function shortcodeOptionsToJsValues($values) : string
    {
        $result = '';
        foreach ($values as $key => $value) {
            // arrays
            if ($this->startsWith($value, '[')) {
                $value = str_replace('[', "['", $value);
                $value = str_replace(']', "']", $value);
                $value = str_replace(',', "', '", $value);
                $value = str_replace("['[", "[[", $value);
                $value = str_replace("]']", "]]", $value);
                $value = str_replace("]', ' [", "], [", $value);
                $result .= "$key: $value,\n";
                #$value = substr($value, 1, strlen($value) - 2);
                #$result .= "$key: {$this->convertArrayToJsRepresentation(explode(',', $value))},\n";
            }
            // booleans
            else if ($value === "true" || $value === "false") {
                $result .= "$key: $value,\n";
            }
            // strings
            else {
                $result .= "$key: '$value',\n";
            }
        }
        return $result;
    }


    /*
     *
     */
    private function convertArrayToJsRepresentation($values) : string
    {
        if (count($values) > 0) {
            $jsStringLiteralArray = "['" . implode("','", $values) . "']";
        } else {
            $jsStringLiteralArray = "[]";
        }
        return $jsStringLiteralArray;
    }


    /*
     *
     */
    /** @noinspection PhpSameParameterValueInspection */
    private function startsWith($haystack, $needle) : bool
    {
        $length = strlen( $needle );
        return substr( $haystack, 0, $length ) === $needle;
    }

}
