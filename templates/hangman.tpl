{extends file="layout.tpl"}

{block name=title}Spânzurătoarea{/block}

{block name=search}{/block}

{block name=content}
  <script>
   var word = "{$word}";
   var difficulty = "{$difficulty}";
  </script>

  <div class="panel panel-default">
    <div class="panel-heading">
      <h3 class="panel-title">Spânzurătoarea</h3>
    </div>
    <div class="panel-body">
      <form id="hangman" action="">

        <div class="graphics">
          <label>Vieți rămase: <span id="livesLeft">6</span></label>
          <div class="hangmanPic"> </div>
          <div class="imageLicense">imagini © dexonline.ro</div>

          <div class="output">
            {section name="ignored" start=0 loop=$wordLength}
              <input style="width:15pt" class="letters" name="out[]" type="text" readonly="readonly" size="1" value="" />
            {/section}
          </div>
        </div>

        <div class="controls">
          {foreach from=$letters item=letter key=i}
            <input class="letterButtons btn" type="button" value="{$letter|mb_strtoupper}"/>
          {/foreach}
          <input id="hintButton" type="button" value="Dă-mi un indiciu" class="btn" />
        </div>

        <div class="newGameControls">
          <label>Joc nou:</label>
          <input class="btn btn-info" type="button" name="newGame_1" value="ușor"/>
          <input class="btn btn-info" type="button" name="newGame_2" value="mediu"/><br/>
          <input class="btn btn-info" type="button" name="newGame_3" value="dificil"/>
          <input class="btn btn-info" type="button" name="newGame_4" value="expert"/>
        </div>

        <div id="resultsWrapper" class="txt">
          {foreach from=$searchResults item=row}
            {include file="bits/definition.tpl" row=$row}
          {/foreach}
        </div>
      </form>
    </div>
  </div>
{/block}
