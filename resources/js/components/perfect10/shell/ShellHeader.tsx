import FlavourDropdown from './FlavourDropdown';
import ProfileIdentity from './ProfileIdentity';

export default function ShellHeader() {
    return (
        <>
            <div className="headerLogo">
                <button
                    id="profileDropdownToggle"
                    type="button"
                    aria-label="Profile menu"
                    disabled
                >
                    <img
                        src="/assets/logos/FullLogo_Transparent_smallr.png"
                        alt="Perfect10"
                    />
                </button>
            </div>

            <div id="shellHeader">
                <div className="profileRow">
                    <ProfileIdentity />
                    <FlavourDropdown />
                </div>

                <div className="profileTickerRow" />
            </div>
        </>
    );
}
