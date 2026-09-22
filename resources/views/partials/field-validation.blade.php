@once
<style>
input.field-invalid,select.field-invalid,textarea.field-invalid{border:2px solid #c62828!important;background:#fff4f3!important;outline-color:#c62828!important;scroll-margin-top:180px}.field-error-message{display:block;color:#b42318;font:600 13px/1.5 Arial,sans-serif;margin:6px 0 12px;white-space:normal}.plant-question.has-field-error,.plant-repeat-row.has-field-error{border-left:3px solid #c62828;padding-left:14px}.field-invalid:focus{outline:2px solid #c62828;outline-offset:2px}
</style>
<script type="application/json" id="field-validation-data">@json(['errors'=>$errors->getMessages(),'form'=>old('_validation_form')])</script>
<script src="{{ asset('js/field-validation.js') }}" defer></script>
@endonce
