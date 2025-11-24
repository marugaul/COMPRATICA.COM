<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Foursquare API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>🧪 Prueba de Foursquare API</h2>
        <p class="text-muted">Vamos a buscar 10 restaurantes en San José, Costa Rica</p>

        <div class="card mt-4">
            <div class="card-body">
                <button id="btnTest" class="btn btn-primary btn-lg">
                    🚀 Probar API de Foursquare
                </button>

                <div id="loading" style="display: none;" class="mt-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <span class="ms-2">Consultando Foursquare...</span>
                </div>

                <div id="results" class="mt-4"></div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('btnTest').addEventListener('click', async function() {
        const btn = this;
        const loading = document.getElementById('loading');
        const results = document.getElementById('results');

        btn.disabled = true;
        loading.style.display = 'block';
        results.innerHTML = '';

        try {
            // Tu API Key
            const apiKey = 'RELC3VJB43LJFSHPISXMK4LPR5QBYUD4U4ZRX5GAASIF4Y45';

            // Buscar restaurantes en San José, Costa Rica
            const url = 'https://api.foursquare.com/v3/places/search?' + new URLSearchParams({
                near: 'San José, Costa Rica',
                categories: '13065', // Restaurantes
                limit: 10
            });

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Authorization': apiKey,
                    'Accept': 'application/json'
                }
            });

            loading.style.display = 'none';

            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }

            const data = await response.json();
            const places = data.results || [];

            if (places.length === 0) {
                results.innerHTML = '<div class="alert alert-warning">No se encontraron resultados</div>';
                btn.disabled = false;
                return;
            }

            // Mostrar resultados
            let html = '<div class="alert alert-success"><strong>✅ ¡API funciona!</strong> Se encontraron ' + places.length + ' restaurantes</div>';
            html += '<table class="table table-striped">';
            html += '<thead><tr><th>Nombre</th><th>Dirección</th><th>Categoría</th></tr></thead><tbody>';

            places.forEach(place => {
                html += '<tr>';
                html += '<td><strong>' + (place.name || 'Sin nombre') + '</strong></td>';
                html += '<td>' + (place.location?.formatted_address || 'Sin dirección') + '</td>';
                html += '<td>' + (place.categories?.[0]?.name || 'Sin categoría') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            // Mostrar JSON completo del primero para debug
            html += '<details class="mt-3"><summary>📋 Ver datos completos del primer resultado</summary>';
            html += '<pre class="bg-light p-3 mt-2">' + JSON.stringify(places[0], null, 2) + '</pre>';
            html += '</details>';

            results.innerHTML = html;
            btn.disabled = false;

        } catch (error) {
            loading.style.display = 'none';
            results.innerHTML = '<div class="alert alert-danger"><strong>❌ Error:</strong> ' + error.message + '</div>';
            btn.disabled = false;
        }
    });
    </script>
</body>
</html>
