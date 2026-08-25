<?php

declare(strict_types=1);

$docBasePath = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Backendbase EXAMPLE API</title>
    <script src="https://cdn.jsdelivr.net/npm/@stoplight/elements@9.0.23/web-components.min.js" integrity="sha256-RuWgRClbvVmXcuGl5njoBweKPSzUMiZkClCRfNiNaTg=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@stoplight/elements@9.0.23/styles.min.css" integrity="sha256-pSACIoEI+1Z7dcr/IJxNiqJWrlkd496n07GjhLGiewY=" crossorigin="anonymous">

    <style>
        .sl-panel {
            margin-top: 20px;
        }
        .sl-relative[data-testid="two-column-right"] .sl-code-viewer__scroller:has(.language-json) {
            min-height: 800px;
            resize: vertical;
        }
        .sl-relative[data-testid="two-column-right"] .sl-code-viewer__scroller .language-json {
            height: 800px;
            overflow-wrap: break-word;
        }

        @media screen and (min-width: 1920px) {

            .sl-flex-1[data-testid="two-column-left"] {
                flex: 0;
                min-width: 600px;
            }
            .sl-relative[data-testid="two-column-right"] {
                flex: 1;
                min-width: 800px;
            }
        }
        @media screen and (min-width: 2560px) {

            .sl-flex-1[data-testid="two-column-left"] {
                flex: 0;
                min-width: 800px;
            }
            .sl-relative[data-testid="two-column-right"] {
                flex: 1;
                min-width: 1200px;
            }
        }
    </style>
</head>
<body>
<elements-api
    apiDescriptionUrl="<?= $docBasePath ?>/example-api-merged.yml?v=<?=date('Ymdhis')?>"
    router="history"
    basePath="<?= $docBasePath ?>"
/>
<script>
    window.addEventListener("dblclick", function(event){
        if(event.target.className === 'token string' && event.target.innerText.indexOf('ey') === 1) {
            navigator.clipboard.writeText(event.target.innerText.replace(/"/g, ''));
        }
    });

</script>
</body>
</html>
