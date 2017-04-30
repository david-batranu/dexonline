{extends "layout-admin.tpl"}

{block "title"}Etichetare 3D{/block}

{block "content"}
  <h3>Etichetare 3D: {$visual->path}</h3>

  <p>
    <a class="btn btn-default" href="visual.php">
      <i class="glyphicon glyphicon-arrow-left"></i>
      înapoi la pagina de imagini
    </a>
  </p>

  <div class="row">
    <div class="col-md-6">

      <div class="panel panel-default">
        <div class="panel-heading">
          Adaugă o etichetă nouă
        </div>
        <div class="panel-body">
          <form class="form-horizontal" method="post">
            <input type="hidden" name="id" value="{$visual->id}">

            <div class="form-group">
              <label class="col-sm-3 control-label">
                intrare
              </label>
              <div class="col-sm-9">
                <select id="tagEntryId" class="form-control" name="tagEntryId">
                </select>
              </div>
            </div>

            <div class="form-group">
              <div class="col-sm-offset-3 col-sm-9">
                <button id="addTagButton" type="submit" class="btn btn-success" name="addTagButton">
                  <i class="glyphicon glyphicon-floppy-disk"></i>
                  salvează eticheta
                </button>
              </div>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>

{/block}

