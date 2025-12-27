// Validation des formulaires
document.addEventListener('DOMContentLoaded', function() {
    // Validation formulaire inscription
    const formInscription = document.querySelector('form[action*="register"]');
    if (formInscription) {
        formInscription.addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                confirmPassword.focus();
            }
            
            if (password.value.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères.');
                password.focus();
            }
        });
    }
    
    // Validation email
    const validateEmail = (email) => {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    };
    
    // Auto-calcul du prix avec marge
    const coutInput = document.getElementById('cout_fabrication');
    const prixInput = document.getElementById('prix_vente');
    
    if (coutInput && prixInput) {
        coutInput.addEventListener('blur', function() {
            if (this.value && !prixInput.value) {
                const cout = parseFloat(this.value);
                const marge = cout * 0.5; // 50% de marge
                prixInput.value = (cout + marge).toFixed(2);
            }
        });
    }
});

// Gestion des notifications
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Animation
    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Gestion du menu mobile
const menuToggle = document.querySelector('.menu-toggle');
const mainNav = document.querySelector('.main-nav');

if (menuToggle) {
    menuToggle.addEventListener('click', function() {
        mainNav.classList.toggle('show');
    });
}

// Menu responsive mobile
const menuToggle = document.querySelector('.menu-toggle');
const mainNavUl = document.querySelector('.main-nav ul');
if (menuToggle && mainNavUl) {
    menuToggle.addEventListener('click', function() {
        mainNavUl.classList.toggle('open');
    });
}

// Confirmation de suppression
function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer ?') {
    return confirm(message);
}

// Formatage des prix
function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(price);
}

// AJAX pour l'ajout au panier
async function addToCart(productId, productName) {
    try {
        const response = await fetch('../includes/ajouter_panier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id_meuble=${productId}&quantite=1`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`${productName} ajouté au panier !`);
            // Mettre à jour le compteur du panier
            const cartCount = document.querySelector('.panier-count');
            if (cartCount && data.count) {
                cartCount.textContent = data.count;
                cartCount.style.display = 'flex';
            }
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'ajout au panier', 'error');
    }
}