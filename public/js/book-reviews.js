document.addEventListener('DOMContentLoaded', function() {
    const reviewsContainer = document.getElementById('reviews-container'); 
    const sortSelect = document.getElementById('review-sort'); 

    // Function to unblur review content
    function unblurReview(button) {
        const reviewContent = button.previousElementSibling;
        reviewContent.classList.remove('blurred');
        button.style.display = 'none';
    }

    // Function to handle review voting
    function voteReview(reviewId, voteType) {
        fetch(`/review/${reviewId}/vote?type=${voteType}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reviewId: reviewId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.upvotes !== undefined && data.downvotes !== undefined) {
                const reviewCard = document.querySelector(`.review-card[data-review-id="${reviewId}"]`);
                reviewCard.querySelector('.upvote-btn').innerHTML = `<i class="fas fa-thumbs-up"></i> (${data.upvotes})`;
                reviewCard.querySelector('.downvote-btn').innerHTML = `<i class="fas fa-thumbs-down"></i> (${data.downvotes})`;
            } else if (data.error) {
                alert(data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    // Function to load reviews with sorting option
    function loadReviews(sort = 'recent', bookId) {
        fetch(`/book/${bookId}/reviews?sort=${sort}`)
            .then(response => response.json())
            .then(data => {
                // Update the reviews container with the fetched reviews
                reviewsContainer.innerHTML = data.reviews.length > 0 
                    ? data.reviews.map(review => `
                    <div class="review-card" data-review-id="${review.id}">
                        <div class="review-card-header">
                            <div class="reviewer-profile">
                                <div class="reviewer-avatar">${review.userInitial}</div>
                                <div class="reviewer-details">
                                    <span class="reviewer-name">
                                        <a href="/profile/${review.username}">
                                            ${review.username}
                                        </a>
                                    </span>
                                    <span class="review-date">Reviewed on ${review.createdAt}</span>
                                </div>
                            </div>
                            <div class="review-rating">
                                <div class="rating-badge">
                                    <span class="rating-value">${review.rating}</span>
                                    <span class="rating-max">/10</span>
                                </div>
                            </div>
                        </div>
                        <div class="review-card-body">
                            <div class="review-content ${review.containsSpoilers ? 'blurred' : ''}">
                                ${review.content}
                            </div>
                            ${review.containsSpoilers 
                                ? '<button class="unblur-btn" onclick="unblurReview(this)">Unblur Spoilers</button>' 
                                : ''}
                            <div class="review-votes">
                                <button class="vote-btn upvote-btn" onclick="voteReview(${review.id}, 'upvote')">
                                    <i class="fas fa-thumbs-up"></i> (${review.upvotes})
                                </button>
                                <button class="vote-btn downvote-btn" onclick="voteReview(${review.id}, 'downvote')">
                                    <i class="fas fa-thumbs-down"></i> (${review.downvotes})
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('') 
                : `
                <div class="no-reviews">
                    <div class="no-reviews-content">
                        <i class="fas fa-book-open"></i>
                        <h3>No Reviews Yet</h3>
                        <p>Be the first to share your thoughts on this book!</p>
                    </div>
                </div>
                `;
                
                attachVoteEventListeners();
            });
    }

    // Attach event listeners for voting actions
    function attachVoteEventListeners() {
        const upvoteButtons = document.querySelectorAll('.upvote-btn');
        const downvoteButtons = document.querySelectorAll('.downvote-btn');

        upvoteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.closest('.review-card').getAttribute('data-review-id');
                voteReview(reviewId, 'upvote');
            });
        });

        downvoteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.closest('.review-card').getAttribute('data-review-id');
                voteReview(reviewId, 'downvote');
            });
        });
    }

    // Initialize reviews
    function initReviews() {
        const sortSelect = document.getElementById('review-sort');
        const bookId = document.querySelector('[data-book-id]').getAttribute('data-book-id');

        // Initial load of reviews
        loadReviews('recent', bookId);

        // Handle sort changes
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                loadReviews(this.value, bookId);
            });
        }
    }

    // Run initialization
    initReviews();

    // Expose functions globally if needed
    window.unblurReview = unblurReview;
    window.voteReview = voteReview;
});