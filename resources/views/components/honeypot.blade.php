{{--
    Spam trap for public forms: put <x-honeypot /> inside the <form>.
    People never see it (moved off-screen, skipped by Tab, hidden from screen readers); robots fill it in.
    Controllers check it with App\Support\Honeypot::tripped($request).
    "display:none" is avoided on purpose: some smarter robots skip fields hidden that way.
--}}
<div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
    <label for="{{ App\Support\Honeypot::FIELD }}">Leave this field empty</label>
    <input type="text" id="{{ App\Support\Honeypot::FIELD }}" name="{{ App\Support\Honeypot::FIELD }}" value="" tabindex="-1" autocomplete="off">
</div>
