<div class='suggestedIdentifier'>
{if count($suggestedIdentifiers) == 0}
  Sorry, we couldn't find an ISBN or OCLC Number for that title, please try changing the title and author and searching again.
{else}
  <table class="suggestedIdentifierTable">
    <tbody>
      {foreach from=$suggestedIdentifiers item=suggestion key=rownum}
      {if $suggestion.isbn}
        {assign var=isn value=$suggestion.isbn}
      {elseif $suggestion.oclcNumber}
        {assign var=isn value=$suggestion.oclcNumber}
      {/if}
      <div class="result clearfix" data-isbn_oclc="{$suggestion.isbn}--{$suggestion.oclcNumber}">
        <div class="image-identifier">
          {if $isn}<img src="{$path}/bookcover.php?isn={$isn}&size=small" alt="book cover"/>{else}&nbsp;{/if}
          <input type="button" value="Use This" onclick="setIsbnAndOclcNumberAnythink('{$suggestion.title|escape}', '{$suggestion.author|escape}', '{$suggestion.isbn}', '{$suggestion.oclcNumber}')" />
        </div>
        <div class="desc-identifier">
          <h3>{$suggestion.title}</h3>
          <h4>{$suggestion.author|truncate:60}</h4>
          <div><a target="_blank" href="{$suggestion.link}">WorldCat link &raquo;</a></div>
          <div class="form-item" id="worldCatDescription{$rownum}">
            <div class="short">
            {$suggestion.description|truncate:150|escape}
            <a href="#" onclick="{literal}${/literal}('.short', '#worldCatDescription{$rownum}').hide();{literal}${/literal}('.full', '#worldCatDescription{$rownum}').slideDown().show();return false;">More</a>
            </div>
            <div class="full" style="display:none;">
            {$suggestion.description|escape}
            <a href="#" onclick="{literal}${/literal}('.full', '#worldCatDescription{$rownum}').hide();{literal}${/literal}('.short', '#worldCatDescription{$rownum}').show();return false;">Less</a>
            </div>
          </div>
          <div class="fine-print worldCatCitaion"><strong>{translate text="Citation"}</strong>&nbsp;&nbsp;{$suggestion.citation}</div>
          <div class="fine-print"><strong>{translate text="ISBN"}</strong>&nbsp;&nbsp;{$suggestion.isbn}</div>
          <div class="fine-print"><strong>{translate text="OCLC"}</strong>&nbsp;&nbsp;{$suggestion.oclcNumber}</div>
        </div>
      </div>
      {/foreach}
    </tbody>
  </table>
{/if}
</div>
