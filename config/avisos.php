<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Canal de los avisos al cliente
    |--------------------------------------------------------------------------
    |
    | El sistema ya detecta cuándo hay que avisar al cliente (su entrega sale
    | hoy, su reparación está lista, su cuota vence). Cómo se le hace llegar es
    | lo que se elige aquí.
    |
    | Canales disponibles:
    |   · `log`    — deja el mensaje en el log. Es el valor por defecto: el
    |                sistema funciona sin contratar nada y los avisos se pueden
    |                revisar mientras se decide el proveedor.
    |   · `correo` — lo manda por Mail al correo del cliente, si tiene.
    |
    | Para WhatsApp o SMS hace falta un proveedor (Twilio, Meta Cloud API, un
    | agregador local…). El punto de enganche es `AvisosAlCliente::enviar()`:
    | se añade el canal y se elige con `AVISOS_CANAL` sin tocar los disparadores.
    |
    */
    'canal' => env('AVISOS_CANAL', 'log'),

];
