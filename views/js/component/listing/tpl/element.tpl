<div class="notif-element {{status}}">
    <div class="container-icon">
        <div class="shape">
            <span class="icon-{{icon}}"/>
        </div>
    </div>
    <div class="container-text">
        <div class="label">{{label}}</div>
        <div class="time">{{time}}</div>
    </div>
    <div class="action-bar action-top">
        <span data-role="delete" class="icon-bin" title="{{__ "remove from the list"}}"/>
    </div>
    <div class="action-bar action-bottom">
        <span data-role="notify" class="icon-preview" title="{{__ "notify me when done"}}"/>
        <span data-role="download" class="icon-download {{#unless file}}hidden{{/unless}}" title="{{__ "download"}}"/>
        <span data-role="report" class="icon-document" title="{{__ "see report"}}"/>
    </div>
</div>