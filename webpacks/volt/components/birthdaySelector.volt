{# Birthday Selector Component #}

{# script template #}
<script type="text/html" id="vue-template-birthday-selector">

    <div class="columns small-12">
        <h6 class="subheader text-center"><i>{{ trans._('Fecha de Nacimiento') }}</i></h6>
        <ul class="menu expanded">
            <li class="text-center">
                {{ select('birthdaySubfield', birthday_elements[2], 'id': '',
                          'data-fv-required' : 'integer: {}', 'data-fv-message' : trans._("Ingresa tu fecha de nacimiento."),
                          'v-model' : 'day') }}
            </li>
            <li class="text-center">
                {{ select('birthdaySubfield', birthday_elements[1], 'id': '',
                           'data-fv-required' : 'integer: {}', 'data-fv-message' : trans._("Ingresa tu fecha de nacimiento."),
                           'v-model' : 'month') }}
            </li>
            <li class="text-center">
                {{ select('birthdaySubfield', birthday_elements[0], 'id': '',
                          'data-fv-required' : 'integer: {}', 'data-fv-message' : trans._("Ingresa tu fecha de nacimiento."),
                           'v-model' : 'year') }}
            </li>
        </ul>
        <input name="birthday" type="hidden" v-model="birthdayValue" />
    </div>

</script>
