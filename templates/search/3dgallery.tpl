{if !empty($images)}
  {include "bits/galleryCanvas.tpl"}

  <div id="gallery">
    <div class="panel panel-default">
      <div class="panel-heading">3d</div>
      <div class="panel-body">
        {foreach $models as $i}
          <a class="gallery"
             href="#idk"
             data-visual-id="{$i->id}"
             title="Imagine: {$i->getTitle()}">
            {$i->getTitle()}
          </a>
        {/foreach}
      </div>
    </div>
  </div>

{/if}
