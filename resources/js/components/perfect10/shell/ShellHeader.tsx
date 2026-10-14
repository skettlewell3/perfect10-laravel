import FlavourDropdown from './FlavourDropdown';
import ProfileDropdown from './ProfileDropdown';
import ProfileIdentity from './ProfileIdentity';
import ProfileTickerRow from './ProfileTickerRow';

export default function ShellHeader() {
    return (
        <>
            <div className="headerLogo">
                <ProfileDropdown />
            </div>

            <div id="shellHeader">
                <div className="profileRow">
                    <ProfileIdentity />
                    <FlavourDropdown />
                </div>

                <ProfileTickerRow />
            </div>
        </>
    );
}
