{extends "layout-admin.tpl"}

{block "title"}Etichetare imagine{/block}

{block "content"}
<script src="{$wwwRoot}js/third-party/three/three.js/build/three.js"></script>
<script src="{$wwwRoot}js/third-party/three/three.js/examples/js/controls/OrbitControls.js"></script>
<script src="{$wwwRoot}js/third-party/three/three.js/examples/js/Detector.js"></script>

<div style="display: flex; justify-content: space-around;">
<div id="3deditor"></div>
<div id="debug" style="border: 1px solid black; min-width: 300px; height: 600px"></div>
</div>

<script src="{$wwwRoot}js/visual3DTag.js"></script>

{/block}

