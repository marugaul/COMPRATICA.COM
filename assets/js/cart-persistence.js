// Sistema de persistencia del carrito
const CartPersistence = {
    // Verificar si el usuario está logueado
    isLoggedIn: function() {
        // Esto se puede verificar con una variable PHP o mediante AJAX
        return typeof userLoggedIn !== 'undefined' && userLoggedIn === true;
    },

    // Guardar carrito en localStorage (para invitados)
    saveToLocalStorage: function(cart) {
        localStorage.setItem('guest_cart', JSON.stringify(cart));
    },

    // Obtener carrito de localStorage
    getFromLocalStorage: function() {
        const cart = localStorage.getItem('guest_cart');
        return cart ? JSON.parse(cart) : {};
    },

    // Limpiar localStorage
    clearLocalStorage: function() {
        localStorage.removeItem('guest_cart');
    },

    // Agregar producto al carrito
    addToCart: function(productId, quantity, productData) {
        if (this.isLoggedIn()) {
            // Usuario logueado - guardar en base de datos
            return fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateCartUI(data.cart, data.count);
                }
                return data;
            });
        } else {
            // Usuario invitado - guardar en localStorage
            let cart = this.getFromLocalStorage();

            if (cart[productId]) {
                cart[productId].quantity += quantity;
            } else {
                cart[productId] = {
                    product_id: productId,
                    quantity: quantity,
                    ...productData
                };
            }

            this.saveToLocalStorage(cart);
            this.updateCartUI(cart, Object.keys(cart).length);

            return Promise.resolve({ success: true, cart: cart });
        }
    },

    // Obtener carrito
    getCart: function() {
        if (this.isLoggedIn()) {
            return fetch('api/cart.php?action=get')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateCartUI(data.cart, data.count);
                    }
                    return data;
                });
        } else {
            const cart = this.getFromLocalStorage();
            this.updateCartUI(cart, Object.keys(cart).length);
            return Promise.resolve({ success: true, cart: cart });
        }
    },

    // Actualizar cantidad
    updateQuantity: function(productId, quantity) {
        if (this.isLoggedIn()) {
            return fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateCartUI(data.cart, data.count);
                }
                return data;
            });
        } else {
            let cart = this.getFromLocalStorage();

            if (quantity > 0) {
                if (cart[productId]) {
                    cart[productId].quantity = quantity;
                }
            } else {
                delete cart[productId];
            }

            this.saveToLocalStorage(cart);
            this.updateCartUI(cart, Object.keys(cart).length);

            return Promise.resolve({ success: true, cart: cart });
        }
    },

    // Eliminar producto
    removeFromCart: function(productId) {
        if (this.isLoggedIn()) {
            return fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateCartUI(data.cart, data.count);
                }
                return data;
            });
        } else {
            let cart = this.getFromLocalStorage();
            delete cart[productId];
            this.saveToLocalStorage(cart);
            this.updateCartUI(cart, Object.keys(cart).length);

            return Promise.resolve({ success: true, cart: cart });
        }
    },

    // Limpiar carrito
    clearCart: function() {
        if (this.isLoggedIn()) {
            return fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateCartUI({}, 0);
                }
                return data;
            });
        } else {
            this.clearLocalStorage();
            this.updateCartUI({}, 0);
            return Promise.resolve({ success: true });
        }
    },

    // Actualizar UI del carrito
    updateCartUI: function(cart, count) {
        // Actualizar contador del carrito
        const cartBadge = document.querySelector('.cart-badge, #cart-count');
        if (cartBadge) {
            cartBadge.textContent = count;
        }

        // Disparar evento personalizado para que otros componentes se actualicen
        window.dispatchEvent(new CustomEvent('cartUpdated', { 
            detail: { cart: cart, count: count } 
        }));
    },

    // Inicializar al cargar la página
    init: function() {
        this.getCart();
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    CartPersistence.init();
});
