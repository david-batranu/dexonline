{extends file="admin/layout.tpl"}

{block name=title}Editare model{/block}

{block name=headerTitle}
  Editare model {$modelType}{$modelNumber}
{/block}

{block name=content}
  {assign var="adjModels" value=$adjModels|default:null}
  {assign var="participles" value=$participles|default:null}
  {assign var="regenTransforms" value=$regenTransforms|default:null}
  {if $wasPreviewed && count($errorMessage) == 0}
    Examinați modificările afișate mai jos (dacă există) și, dacă totul
    arată normal, apăsați butonul "Salvează". Dacă nu, continuați editarea
    și apăsați din nou butonul "Testează".
    <br/><br/>
  {/if}

  <form action="editModel.php" method="post">
    <input type="hidden" name="modelType" value="{$modelType}"/>
    <input type="hidden" name="modelNumber" value="{$modelNumber}"/>

    <table class="editModel">
      <tr>
        <td>
          Număr model
          <span class="small">(poate conține orice caractere)</span>
        </td>
        <td></td>
        <td class="input">
          <input type="text" name="newModelNumber"
                 value="{$newModelNumber|escape}"/>

          <span class="tooltip2" title="Aici puteți edita exponentul ales pentru un model și formele pentru diversele flexiuni. Folosiți accente unde
                                        doriți. Dacă o flexiune nu are forme, lăsați câmpul vid. Dacă o flexiune are mai multe forme, apăsați semnul + pentru a obține câte câmpuri
                                        doriți. Pentru a șterge o formă, ștergeți conținutul câmpului respectiv. Dacă bifați/debifați o formă pentru LOC, rezultatul se va aplica
                                        tuturor formelor corespunzătoare ale lexemelor din acest model, dar nu și la alte modele. Tipul modelului nu este editabil, dar numărul
                                        este.">&nbsp;</span>

        </td>
      </tr>
      <tr>
        <td>Descriere</td>
        <td></td>
        <td class="input">
          <input type="text" name="newDescription"
                 value="{$newDescription|escape}"/>
        </td>
      </tr>
      {if $adjModels}
        <tr>
          <td>Model de participiu</td>
          <td></td>
          <td class="input">
            <select name="newParticipleNumber">
              {foreach from=$adjModels item=m}
                <option value="{$m->number}"
                        {if $m->number == $newParticipleNumber
                        }selected="selected"{/if}
                        >{$m->number}{if !$m->id}*{/if} ({$m->exponent})
                </option>
              {/foreach}
            </select>
          </td>
        </tr>
      {/if}
      <tr class="exponent">
        <td>Exponent</td>
        <td></td>
        <td class="input">
          <input type="text" name="newExponent" value="{$newExponent|escape}"/>
        </td>
      </tr>

      <tr>
        <th>Flexiune</th>
        <th></th>
        <th class="input">
          <span class="fieldColumn">Forme</span>
          <span class="checkboxColumn">LOC</span>
          <span class="checkboxColumn">Recom.</span>
        </th>
      </tr>

      {foreach from=$newForms item=forms key=inflId}
        <tr class="{cycle values="odd,even"}">
          <td>{$inflectionMap[$inflId]->description|escape}</td>
          <td class="addSign">
            <a class="noBorder" href="#" onclick="return editModelAppendBox({$inflId})">
              <img src="{$imgRoot}/icons/add.png" alt="plus"/>
            </a>
          </td>
          <td class="input" id="td_{$inflId}">
            {foreach from=$forms item=tuple key=i}
              <p>
                <input class="fieldColumn" type="text" name="forms_{$inflId}_{$i}" value="{$tuple.form|escape}"/>
                <input class="checkboxColumn" type="checkbox" name="isLoc_{$inflId}_{$i}" value="1" {if $tuple.isLoc}checked="checked"{/if}/>
                <input class="checkboxColumn" type="checkbox" name="recommended_{$inflId}_{$i}" value="1" {if $tuple.recommended}checked="checked"{/if}/>
              </p>
            {/foreach}
          </td>
        </tr>
      {/foreach}
    </table>

    {if $wasPreviewed}
      {if $newModelNumber != $modelNumber ||
      $newExponent != $exponent ||
      $newDescription != $description ||
      $newParticipleNumber != $participleNumber}
        <h3>Schimbări globale:</h3>

        <ul>
          {if $newModelNumber != $modelNumber}
            <li>Număr de model nou: {$newModelNumber|escape}</li>
          {/if}
          {if $newExponent != $exponent}
            <li>Exponent nou: {$newExponent|escape}</li>
          {/if}
          {if $newDescription != $description}
            <li>Descriere nouă: {$newDescription|escape}</li>
          {/if}
          {if $newParticipleNumber != $participleNumber}
            <li>Model nou de participiu: A{$newParticipleNumber|escape}</li>
          {/if}
        </ul>
      {/if}

      {if count($regenTransforms)}
        <h3>Lista de flexiuni afectate ({$regenTransforms|@count}):</h3>
        <ol>
          {foreach from=$regenTransforms item=ignored key=inflId}
            <li>{$inflectionMap[$inflId]->description|escape}</li>
          {/foreach}
        </ol>

        <h3>Lexemele afectate ({$lexemModels|@count}) și noile lor forme:</h3>

        <table class="changedForms">
          <tr class="header">
            <td class="lexem">Lexem</td>
            <td class="model">Model</td>
            {foreach from=$regenTransforms item=ignored key=ignored2}
              <td class="forms">{counter name="otherCounter"}.</td>
            {/foreach}
          </tr>
          <tr class="exponent">
            <td class="lexem">{$newExponent}</td>
            <td class="model">exponent</td>
            {foreach from=$regenTransforms item=ignored key=inflId}
              {assign var="variantArray" value=$newForms[$inflId]}
              <td class="forms">
                {strip}
                {foreach from=$variantArray item=tuple key=i}
                  {if $i}, {/if}
                  {$tuple.form|escape}
                {/foreach}
              {/strip}
              {if !count($variantArray)}&mdash;{/if}
              </td>
            {/foreach}
          </tr>
          {foreach from=$lexemModels item=lm key=lIndex}
            {assign var="inflArray" value=$regenForms[$lIndex]}
            <tr>
              <td class="lexem">{$lm->getLexem()->form|escape}</td>
              <td class="model">{$lm->modelType}{$lm->modelNumber}</td>
              {foreach from=$inflArray item=variantArray key=inflId}
                <td class="forms">
                  {strip}
                  {foreach from=$variantArray item=form key=i}
                    {if $i}, {/if}
                    {$form|escape}
                  {/foreach}
                  {if !count($variantArray)}&mdash;{/if}
                {/strip}
                </td>
              {/foreach}
            </tr>
          {/foreach}
        </table>
      {/if}
    {/if}

    {if count($participles)}
      <h3>Participii regenerate conform modelului A{$newParticipleNumber}:</h3>

      {foreach from=$participles item=p key=i}
        {include file="paradigm/paradigm.tpl" lexemModel=$p}
      {/foreach}
    {/if}

    <br/>
    <input type="submit" name="previewButton" value="Testează"/>
    <!-- We want to disable the button on click, but still submit a value -->
    {if $wasPreviewed && count($errorMessage) == 0}
      <input type="submit" name="confirmButton" value="Salvează"/>
    {/if}
  </form>
{/block}