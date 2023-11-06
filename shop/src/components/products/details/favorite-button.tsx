import { HeartFillIcon } from '@/components/icons/heart-fill';
import { HeartOutlineIcon } from '@/components/icons/heart-outline';
import Spinner from '@/components/ui/loaders/spinner/spinner';
import { useModalAction } from '@/components/ui/modal/modal.context';
import { useUser } from '@/framework/user';
import { useInWishlist, useToggleWishlist } from '@/framework/wishlist';
import classNames from 'classnames';

function FavoriteButton({
  productId,
  className,
}: {
  productId: string;
  className?: string;
}) {
  const { isAuthorized } = useUser();
  const { toggleWishlist, isLoading: adding } = useToggleWishlist(productId);
  const { inWishlist, isLoading: checking } = useInWishlist({
    enabled: isAuthorized,
    product_id: productId,
  });

  const { openModal } = useModalAction();
  function toggle() {
    if (!isAuthorized) {
      openModal('LOGIN_VIEW');
      return;
    }
    toggleWishlist({ product_id: productId });
  }
  const isLoading = adding || checking;
  if (isLoading) {
    return (
      <div
        className={classNames(
          'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-300',
          className
        )}
      >
        <Spinner simple={true} className="flex w-5 h-5" />
      </div>
    );
  }
  return (
    <button
      type="button"
      className={classNames(
        'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-300 transition-colors',
        {
          '!border-accent': inWishlist,
        },
        className
      )}
      onClick={toggle}
    >
      {inWishlist ? (
        <HeartFillIcon className="w-5 h-5 text-accent" />
      ) : (
        <HeartOutlineIcon className="w-5 h-5 text-accent" />
      )}
    </button>
  );
}

export default FavoriteButton;
