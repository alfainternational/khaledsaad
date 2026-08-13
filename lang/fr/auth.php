<?php

/*
 * `failed` ne distingue pas « adresse inconnue » de « mot de passe erroné » :
 * la distinction transformerait le formulaire en outil d’énumération.
 */

return [

    'failed' => 'Adresse e-mail ou mot de passe incorrect.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds secondes.',

];
