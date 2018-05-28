{extends "layout.tpl"}

{block "title"}Surse favorite{/block}

{block "content"}

  <form method="post" action="surseFavorite" name="surse">

    <h3>Definiții existente</h3>
    {if $selections}
      {foreach $selections as $sel}
        <dl>
          <dt>Denumire</dt>
          <dd>{$sel->name}</dd>
          <dt>Dicționare</dt>
          <dd>
            <ul>
              {foreach $sel->getSources() as $source}
                <li>{$source->name}</li>
              {/foreach}
            </ul>
          </dd>
        </dl>
      {/foreach}
    {else}
      <p>Nu ai surse definite.</p>
    {/if}

    <h3>Adaugă o sursă favorită</h3>
    <label> Denumire
      <input type="text" name="selectionName">
    </label>
    <br>
    <select multiple="multiple" name="sourceId[]">
      {foreach $sources as $source}
        <option value="{$source->id}">{$source->name}</option>
      {/foreach}
    </select>
    <br>
    <input type="submit" value="Adaugă" />
  </form>

{/block}
