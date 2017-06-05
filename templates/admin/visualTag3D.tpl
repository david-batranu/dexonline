{extends "layout-admin.tpl"}

{block "title"}Etichetare 3D{/block}

{block "content"}
  <script src="{$wwwRoot}js/third-party/three/three.js"></script>
  <script src="{$wwwRoot}js/third-party/three/controls/OrbitControls.js"></script>
  <script src="{$wwwRoot}js/third-party/three/Detector.js"></script>
  <script src="{$wwwRoot}js/third-party/three/ColladaLoader.js"></script>
  <style>
    .canvas-container {
      position: relative;
    }

    #preview-3d {
      position: absolute;
      z-index: 8;
      border: 1px solid red;
    }

    #overlay-2d {
      position: absolute;
      border: 1px solid black;
      z-index: 10;
    }
  </style>

  <h3>Etichetare 3D: {$visual->path}</h3>

  <p>
    <a class="btn btn-default" href="visual.php">
      <i class="glyphicon glyphicon-arrow-left"></i>
      înapoi la pagina de imagini
    </a>
  </p>

  <div class="row">

      <div class="panel panel-default">
        <div class="panel-heading">
          Preview
        </div>
        <div class="panel-body" style="width: 800px; height: 600px">
          <div class="canvas-container">
              <div
                id="preview-3d" style="text-align: center"
                data-src="{$cfg.static.url}img/visual/{$visual->path}"></div>
              <div id="overlay-2d">
                <canvas width="800" height="600"></canvas>
              </div>
          </div>
        </div>
      </div>

      <div class="panel panel-default">
        <div class="panel-heading">
          Administrare etichete
        </div>
        <div class="panel-body">
          <form class="form-horizontal" method="post">
            <input type="hidden" name="id" value="{$visual->id}">
            <table id="table-assign" class="table table-hover">
              <thead>
                <tr>
                  <th>Nume obiect</th>
                  <th>Etichetă</th>
                  <th>Camera</th>
                <tr>
              </thead>
              <tbody>
                {foreach $tags as $tag}
                  <tr>
                    <td id="{$tag->meshName}"></td>
                    <td><select name="mapping_{$tag->meshName}">
                      <option value="{$tag->entryId}" selected="selected">{$tag->getTitle()}</option>
                    </select>
                    <button class="btn btn-sm btn-danger" name="clearTagButton" value="{$tag->meshName}">
                        <span class="glyphicon glyphicon-trash"></span>
                    </button>
                    </td>
                    <td>
                    <input type="hidden" id="camera_{$tag->meshName}" name="camera_{$tag->meshName}" value="{$tag->camera}" />
                    <button class="btn btn-sm btn-default" data-action="save-camera" data-mesh="{$tag->meshName}">
                        Salvează poziția camerei
                    </button>
                    <button class="btn btn-sm btn-default" data-action="restore-camera" data-mesh="{$tag->meshName}">
                        Aplică poziția camerei
                    </button>
                    </td>
                  </tr>
                {/foreach}
              </tbody>
            </table>

            <button type="submit" class="btn btn-success" name="addTagButton">
              <i class="glyphicon glyphicon-floppy-disk"></i>
              salvează etichete
            </button>
          </form>
        </div>
      </div>

  </div>

  <script>window.start3d();</script>

{/block}
