@extends('errors.layout')

@section('code', '423')
@section('title', 'الحساب مقفول دلوقتي')
@section('message', $exception->getMessage() ?: 'حسابك مقفول في الوقت ده.')
