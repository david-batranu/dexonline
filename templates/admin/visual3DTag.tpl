{extends "layout-admin.tpl"}

{block "title"}Etichetare imagine{/block}

{block "content"}
<script src="{$wwwRoot}js/third-party/three/three.js/build/three.js"></script>
<script src="{$wwwRoot}js/third-party/three/three.js/examples/js/controls/OrbitControls.js"></script>
<!-- script src="{$wwwRoot}js/third-party/three/three.js/examples/js/loaders/FBXLoader2.js"></script-->
<!-- script src="{$wwwRoot}js/third-party/three/three.js/examples/js/loaders/OBJLoader.js"></script-->
<script src="{$wwwRoot}js/third-party/three/three.js/examples/js/Detector.js"></script>
<script src="{$wwwRoot}js/third-party/three/three.js/examples/js/libs/stats.min.js"></script>

<div id="3deditor" style="display: inline-block"></div>
<div id="debug" style="display: inline-block; border: 1px solid black; min-width: 300px; height: 600px"></div>

<script src="{$wwwRoot}js/visual3DTag.js"></script>

{/block}

