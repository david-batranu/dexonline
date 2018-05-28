{assign var="name" value=$name|default:'source'}
{assign var="multiple" value=$multiple|default:false}
{assign var="skipAnySource" value=$skipAnySource|default:''}
{assign var="sourceId" value=$sourceId|default:null}
{assign var="sourceIds" value=$sourceIds|default:[]}
{assign var="urlName" value=$urlName|default:false}
{assign var="width" value=$width|default:'100%'}
{assign var="autosubmit" value=$autosubmit|default:false}
<select name="{$name}[]"
        id="sourceDropDown"
        class="form-control sourceDropDown"
        style="width: {$width}"
        {if $multiple}multiple="multiple"{/if}
        {if $autosubmit}onchange="this.form.submit();"{/if}>
  {if !$skipAnySource}
    <option value="">Toate dicționarele</option>
  {/if}
  {foreach Source::getAll(Source::SORT_SEARCH) as $source}
    {if $urlName}
      {assign var="submitValue" value=$source->urlName}
    {else}
      {assign var="submitValue" value=$source->id}
    {/if}
    {if ($source->type != Source::TYPE_HIDDEN) ||
        User::can(User::PRIV_VIEW_HIDDEN)}
      <option value="{$submitValue}" {if $multiple && in_array($source->id, $sourceIds) || $source->id == $sourceId}selected{/if}>
        {* All the select2-searchable text must go here, not in data-* attributes *}
        ({$source->shortName|escape})
        {$source->name|escape}
      </option>
    {/if}
  {/foreach}
</select>
