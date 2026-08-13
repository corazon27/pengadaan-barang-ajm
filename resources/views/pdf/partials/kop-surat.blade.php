<div class="kop">
    <div class="kop-name">{{ config('company.name') }}</div>
    <div class="kop-entity">
        {{ config('company.legal_entity') }} &mdash; NIB {{ config('company.nib') }}
        &mdash; {{ config('company.pkp') }} &mdash; NPWP {{ config('company.npwp') }}
    </div>
    <div class="kop-address">{{ config('company.address') }}</div>
    <div class="kop-contact">
        Telp: {{ config('company.phone') }} &nbsp;|&nbsp; Email: {{ config('company.email') }}
    </div>
</div>

<div class="kop-rule"></div>
<div class="kop-rule kop-rule-thin"></div>
