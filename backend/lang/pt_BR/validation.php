<?php

return [
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'email' => 'Informe um e-mail válido.',
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'prohibited' => 'O campo :attribute não pode ser informado.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'enum' => 'O valor selecionado para :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => ['string' => 'O campo :attribute deve ter no máximo :max caracteres.'],
    'min' => ['numeric' => 'O campo :attribute deve ser no mínimo :min.', 'string' => 'O campo :attribute deve ter pelo menos :min caracteres.'],
    'between' => ['numeric' => 'O campo :attribute deve estar entre :min e :max.'],
    'attributes' => [
        'destination' => 'destino',
        'departure_date' => 'data de ida',
        'return_date' => 'data de volta',
        'date_from' => 'data inicial',
        'date_to' => 'data final',
        'status' => 'status',
        'email' => 'e-mail',
        'password' => 'senha',
        'per_page' => 'itens por página',
    ],
];
